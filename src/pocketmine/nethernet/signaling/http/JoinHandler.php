<?php

/*
 * This file is part of NetherNet.
 * Copyright (C) 2026 Axolotl Team <https://github.com/axolotl-pm/NetherNet>
 *
 * NetherNet is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\nethernet\signaling\http;

use pocketmine\nethernet\info\ServerInfoCodec;
use pocketmine\nethernet\info\ServerInfoProvider;
use pocketmine\nethernet\negotiation\Negotiation;
use pocketmine\nethernet\negotiation\NegotiationException;
use pocketmine\nethernet\negotiation\Negotiator;
use pocketmine\nethernet\protocol\ErrorCode;
use pocketmine\nethernet\utils\AddressBlockList;
use pocketmine\nethernet\utils\InternetAddress;
use function preg_match;
use function rawurldecode;
use function str_starts_with;
use function strcmp;
use function strlen;
use function substr;

/**
 * @internal
 * The two endpoints NetherNet signals over: `GET /v1/join` probes whether a host is there, and
 * `POST /v1/join/<network id>` delivers an offer and is answered with the SDP answer.
 */
final class JoinHandler{

	private const PATH = "/v1/join";
	private const OFFER_PATH_PREFIX = self::PATH . "/";
	private const MAX_NETWORK_ID = "18446744073709551615";

	public const CONTENT_TYPE_SDP = "application/sdp";
	public const CONTENT_TYPE_JSON = "application/json";
	private const CONTENT_TYPE_TEXT = "text/plain; charset=utf-8";

	public function __construct(
		private readonly Negotiator $negotiator,
		private readonly AddressBlockList $blockList,
		private readonly ?ServerInfoProvider $infoProvider,
		private readonly ?ReverseProxy $reverseProxy,
		private readonly \Logger $logger
	){}

	/**
	 * Decides from the request head alone whether the request can be served, before any of its body is read.
	 *
	 * @return string|null the network ID an offer is made under, or null for a capability probe
	 *
	 * @throws HttpException
	 */
	public function route(HttpRequest $request) : ?string{
		$path = $request->getPath();

		if($path === self::PATH || $path === self::OFFER_PATH_PREFIX){
			if($request->method !== HttpRequest::METHOD_GET && $request->method !== HttpRequest::METHOD_HEAD){
				throw new HttpException(HttpStatus::METHOD_NOT_ALLOWED, "Method not allowed: endpoint only accepts GET or HEAD requests");
			}

			return null;
		}

		if(!str_starts_with($path, self::OFFER_PATH_PREFIX)){
			throw new HttpException(HttpStatus::NOT_FOUND, "No such endpoint");
		}
		if($request->method !== HttpRequest::METHOD_POST){
			throw new HttpException(HttpStatus::METHOD_NOT_ALLOWED, "Method not allowed: SDP offers must be submitted via POST");
		}
		if(!$request->hasContentType(self::CONTENT_TYPE_SDP)){
			throw new HttpException(HttpStatus::UNSUPPORTED_MEDIA_TYPE, "Offers must be sent as " . self::CONTENT_TYPE_SDP);
		}

		$networkId = rawurldecode(substr($path, strlen(self::OFFER_PATH_PREFIX)));
		if($networkId === ""){
			throw new HttpException(HttpStatus::NOT_FOUND, "No NetworkID in the request path");
		}
		if(strlen($networkId) > HttpSignaling::MAX_NETWORK_ID_LENGTH){
			throw new HttpException(HttpStatus::URI_TOO_LONG, "NetworkID is longer than " . HttpSignaling::MAX_NETWORK_ID_LENGTH . " bytes");
		}
		if(preg_match('/^\d{1,20}\z/', $networkId) !== 1 || (strlen($networkId) === 20 && strcmp($networkId, self::MAX_NETWORK_ID) > 0)){
			throw new HttpException(HttpStatus::BAD_REQUEST, "NetworkID must be an unsigned 64-bit integer");
		}

		return $networkId;
	}

	/**
	 * @return HttpResponse|Negotiation|null a response to send, a negotiation whose outcome is the response, or null to
	 *                                       close the connection without answering
	 *
	 * @throws HttpException
	 */
	public function handle(HttpRequest $request, string $body, ?InternetAddress $peerAddress) : HttpResponse|Negotiation|null{
		$networkId = $this->route($request);

		$clientAddress = $peerAddress?->ip;
		if($clientAddress !== null && $this->reverseProxy !== null && $this->reverseProxy->trusts($clientAddress)){
			$clientAddress = $this->reverseProxy->clientAddress($request);
			//probes are allowed through, a proxy's health check names no client
			if($clientAddress === null && $networkId !== null){
				$this->logger->warning("Refused an offer from trusted proxy $peerAddress without a usable " . $this->reverseProxy->getHeader() . " header");

				throw new HttpException(HttpStatus::BAD_REQUEST, "No client address in the " . $this->reverseProxy->getHeader() . " header");
			}
		}
		if($clientAddress !== null && $this->blockList->isBlocked($clientAddress)){
			$this->logger->debug(($peerAddress?->toString() ?? "unknown") . ": dropped a request from $clientAddress, which is blocked");

			return null;
		}

		if($networkId === null){
			return $this->probe();
		}

		try{
			return $this->negotiator->beginNegotiation($body, $networkId, peerAddress: $clientAddress);
		}catch(NegotiationException $e){
			$this->logger->debug("Refused an offer: " . $e->getMessage());

			return self::failure($e->getErrorCode());
		}
	}

	/**
	 * A failure is reported as its error code and nothing else, which is the part a client can act on.
	 */
	private static function failure(ErrorCode $code) : HttpResponse{
		return new HttpResponse(HttpStatus::INTERNAL_SERVER_ERROR, (string) $code->value, self::CONTENT_TYPE_TEXT);
	}

	private function probe() : HttpResponse{
		$info = $this->infoProvider?->getServerInfo();
		if($info !== null){
			try{
				return new HttpResponse(HttpStatus::OK, ServerInfoCodec::encodeJson($info), self::CONTENT_TYPE_JSON);
			}catch(\JsonException $e){
				$this->logger->debug("Could not build the server status: " . $e->getMessage());
			}
		}

		/* A host with nothing to say yet still has to read as a host that speaks NetherNet. */
		return new HttpResponse(HttpStatus::OK);
	}

	/**
	 * Returns the response for a negotiation's outcome, or null while it has none yet.
	 */
	public function respondTo(Negotiation $negotiation) : ?HttpResponse{
		$answer = $negotiation->getAnswer();
		if($answer !== null){
			return new HttpResponse(HttpStatus::OK, $answer, self::CONTENT_TYPE_SDP);
		}
		if($negotiation->isFailed()){
			$this->logger->debug("Negotiation failed: " . ($negotiation->getFailureReason() ?? "no reason given"));

			return self::failure($negotiation->getFailureCode());
		}

		return null;
	}
}
