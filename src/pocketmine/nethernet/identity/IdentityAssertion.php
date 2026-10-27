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

namespace pocketmine\nethernet\identity;

use pocketmine\nethernet\sdp\Fingerprint;
use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;

/**
 * The WebRTC identity assertion (`a=identity`, RFC 8827) exchanged in SDP: a token naming a key, and that key's
 * signature over the connection's DTLS fingerprint.
 */
final class IdentityAssertion{

	public const PROTOCOL_DEFAULT = "default";

	private const KEY_PROVIDER = "idp";
	private const KEY_PROVIDER_DOMAIN = "domain";
	private const KEY_PROVIDER_PROTOCOL = "protocol";
	private const KEY_ASSERTION = "assertion";
	private const KEY_TOKEN = "token";
	private const KEY_FINGERPRINT_SIGNATURE = "fingerprints";

	private function __construct(
		private readonly string $domain,
		private readonly string $protocol,
		private readonly JsonWebToken $token,
		private readonly string $fingerprintSignature
	){
		if($protocol !== self::PROTOCOL_DEFAULT){
			throw new IdentityException("Identity provider protocol must be " . self::PROTOCOL_DEFAULT . ", got $protocol");
		}
		if($domain === ""){
			throw new IdentityException("Identity provider domain must not be empty");
		}
	}

	/**
	 * @param string $fingerprintSignature Detached JWS over the fingerprint's canonical payload
	 *
	 * @throws IdentityException
	 */
	public static function create(string $domain, JsonWebToken $token, string $fingerprintSignature) : self{
		return new self($domain, self::PROTOCOL_DEFAULT, $token, $fingerprintSignature);
	}

	/**
	 * Decodes the base64 value of an `a=identity` attribute.
	 *
	 * @throws IdentityException
	 */
	public static function parse(string $attributeValue) : self{
		$envelope = base64_decode($attributeValue, true);
		if($envelope === false || $envelope === ""){
			throw new IdentityException("Identity attribute is not valid base64");
		}
		$decoded = Json::decodeObject($envelope, "identity envelope");

		$idp = $decoded[self::KEY_PROVIDER] ?? null;
		if(!is_array($idp)){
			throw new IdentityException("Identity has no idp object");
		}
		$domain = $idp[self::KEY_PROVIDER_DOMAIN] ?? null;
		$protocol = $idp[self::KEY_PROVIDER_PROTOCOL] ?? null;
		if(!is_string($domain) || !is_string($protocol)){
			throw new IdentityException("Identity provider is missing its domain or protocol");
		}

		/* The assertion is a JSON document carried as a string, not a nested object. */
		$inner = $decoded[self::KEY_ASSERTION] ?? null;
		if(!is_string($inner)){
			throw new IdentityException("Identity assertion must be a JSON string");
		}
		$assertion = Json::decodeObject($inner, "identity assertion");

		$token = $assertion[self::KEY_TOKEN] ?? null;
		$fingerprintSignature = $assertion[self::KEY_FINGERPRINT_SIGNATURE] ?? null;
		if(!is_string($token) || !is_string($fingerprintSignature)){
			throw new IdentityException("Identity assertion is missing its token or fingerprints");
		}

		return new self($domain, $protocol, JsonWebToken::parse($token), $fingerprintSignature);
	}

	public function getDomain() : string{ return $this->domain; }

	public function getProtocol() : string{ return $this->protocol; }

	public function getToken() : JsonWebToken{ return $this->token; }

	/**
	 * Encodes the assertion into a base64 string for the `a=identity` SDP attribute.
	 *
	 * @throws IdentityException
	 */
	public function encode() : string{
		return base64_encode(Json::encode([
			self::KEY_ASSERTION => Json::encode([
				self::KEY_FINGERPRINT_SIGNATURE => $this->fingerprintSignature,
				self::KEY_TOKEN => $this->token->toString()
			]),
			self::KEY_PROVIDER => [
				self::KEY_PROVIDER_DOMAIN => $this->domain,
				self::KEY_PROVIDER_PROTOCOL => $this->protocol
			]
		]));
	}

	/**
	 * Checks that the key the token names signed this fingerprint.
	 *
	 * @throws IdentityException
	 */
	public function verifyFingerprint(Fingerprint $fingerprint) : void{
		if(!JsonWebSignature::verifyDetached($this->fingerprintSignature, $fingerprint->toCanonicalPayload(), $this->token->getPublicKey())){
			throw new IdentityException("Fingerprint assertion was not signed by the key in the token");
		}
	}
}
