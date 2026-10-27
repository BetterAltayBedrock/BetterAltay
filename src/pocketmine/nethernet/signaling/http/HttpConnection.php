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

use pocketmine\nethernet\crypto\OpenSsl;
use pocketmine\nethernet\negotiation\Negotiation;
use pocketmine\nethernet\utils\InternetAddress;
use function bin2hex;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function implode;
use function is_resource;
use function min;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function str_split;
use function strcasecmp;
use function stream_context_set_option;
use function stream_socket_enable_crypto;
use function stream_socket_recvfrom;
use function strlen;
use function strpos;
use function substr;
use function trim;
use const INF;
use const STREAM_CRYPTO_METHOD_TLS_SERVER;
use const STREAM_PEEK;

/**
 * @internal
 * One client connection, from accept to close: at most one request is read and one response written.
 */
final class HttpConnection{

	public const MAX_HEAD_SIZE = 16384;
	public const MAX_BODY_SIZE = 65536;

	/** Seconds a client has to send its request head, including any TLS handshake. */
	public const HEAD_TIMEOUT = 5.0;
	/** Seconds a client has to send its whole request. */
	public const BODY_TIMEOUT = 10.0;
	/** Seconds a client has to take the response. */
	public const WRITE_TIMEOUT = 10.0;

	private const TLS_HANDSHAKE_RECORD = "\x16";
	private const TLS_MAJOR_VERSION = "\x03";

	private const READ_CHUNK_SIZE = 65536;
	private const MAX_DRAIN_ON_CLOSE = 262144;
	private const CONTINUE_RESPONSE = "HTTP/1.1 100 Continue\r\n\r\n";

	private HttpConnectionState $state;

	/** When the current state gives up waiting. */
	private float $deadline;
	/** When the client could first have started sending its request; the body timeout counts from here. */
	private float $requestStart;

	private string $input = "";
	private string $output = "";

	private ?HttpRequest $request = null;
	private int $bodyOffset = 0;
	private int $contentLength = 0;

	private ?Negotiation $negotiation = null;

	/**
	 * @param resource     $stream Non-blocking stream of the accepted connection.
	 * @param \Socket|null $socket Socket the stream was exported from, held only so that its descriptor outlives the stream.
	 */
	public function __construct(
		private readonly mixed $stream,
		public readonly ?\Socket $socket,
		private readonly ?InternetAddress $peerAddress,
		?TlsConfiguration $tls,
		private readonly \Logger $logger,
		float $now
	){
		$this->requestStart = $now;
		$this->deadline = $now + self::HEAD_TIMEOUT;

		if($tls !== null){
			foreach($tls->getContextOptions() as $option => $value){
				stream_context_set_option($stream, "ssl", $option, $value);
			}
			$this->state = HttpConnectionState::SNIFFING;
		}else{
			$this->state = HttpConnectionState::READING_HEAD;
		}
	}

	public function getState() : HttpConnectionState{ return $this->state; }

	public function getDeadline() : float{ return $this->deadline; }

	public function isDone() : bool{ return $this->state === HttpConnectionState::DONE; }

	public function getPeerName() : string{
		return $this->peerAddress?->toString() ?? "unknown";
	}

	/**
	 * Moves the connection as far as it can go without waiting.
	 */
	public function advance(float $now, JoinHandler $handler) : void{
		try{
			$this->step($now, $handler);
		}catch(HttpException $e){
			$this->logger->debug($this->getPeerName() . ": " . $e->getMessage());
			$this->respond(new HttpResponse($e->getStatus()), $now);
		}catch(\Throwable $e){
			$this->logger->error($this->getPeerName() . ": unhandled " . $e::class . ": " . $e->getMessage());
			$this->logger->logException($e);
			$this->respond(new HttpResponse(HttpStatus::INTERNAL_SERVER_ERROR), $now);
		}
	}

	/**
	 * @throws HttpException
	 */
	private function step(float $now, JoinHandler $handler) : void{
		if($this->state === HttpConnectionState::SNIFFING && !$this->sniff($now)){
			return;
		}
		if($this->state === HttpConnectionState::TLS_HANDSHAKE && !$this->handshake($now)){
			return;
		}
		if(($this->state === HttpConnectionState::READING_HEAD || $this->state === HttpConnectionState::READING_HEAD_TO_REDIRECT) && !$this->readHead($now, $handler)){
			return;
		}

		match($this->state){
			HttpConnectionState::READING_BODY => $this->readBody($now, $handler),
			HttpConnectionState::NEGOTIATING => $this->pollNegotiation($now, $handler),
			HttpConnectionState::WRITING => $this->write($now),
			default => null
		};
	}

	private function transitionTo(HttpConnectionState $state, float $deadline) : void{
		$this->state = $state;
		$this->deadline = $deadline;
	}

	/**
	 * @return bool whether the client's first byte has arrived and said what it is
	 *
	 * @throws HttpException
	 */
	private function sniff(float $now) : bool{
		$peeked = @stream_socket_recvfrom($this->stream, 1, STREAM_PEEK);
		if($peeked === false || $peeked === ""){
			if($now > $this->deadline){
				throw new HttpException(HttpStatus::REQUEST_TIMEOUT, "Timed out waiting for a TLS handshake");
			}
			$this->closeIfPeerGone();

			return false;
		}

		/*
		 * This is only asked of the very first byte: once the handshake is under way, what arrives belongs to it.
		 */
		$this->state = $peeked[0] === self::TLS_HANDSHAKE_RECORD ? HttpConnectionState::TLS_HANDSHAKE : HttpConnectionState::READING_HEAD_TO_REDIRECT;

		return true;
	}

	/**
	 * @return bool whether the handshake has completed
	 *
	 * @throws HttpException
	 */
	private function handshake(float $now) : bool{
		/* A failed handshake only explains itself in a PHP warning, so that is caught rather than suppressed. */
		$warning = null;
		set_error_handler(function(int $severity, string $message) use (&$warning) : bool{
			$warning ??= $message;

			return true;
		});
		try{
			$result = stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
		}finally{
			restore_error_handler();
		}

		if($result === false){
			throw new HttpException(HttpStatus::BAD_REQUEST, "TLS handshake failed: " . ($warning ?? OpenSsl::lastError()));
		}
		if($result !== true){
			if($now > $this->deadline){
				throw new HttpException(HttpStatus::REQUEST_TIMEOUT, "Timed out completing the TLS handshake");
			}
			$this->closeIfPeerGone();

			return false;
		}

		$this->requestStart = $now;
		$this->transitionTo(HttpConnectionState::READING_HEAD, $now + self::HEAD_TIMEOUT);

		return true;
	}

	/**
	 * @return bool whether the head is complete and a body is now expected
	 *
	 * @throws HttpException
	 */
	private function readHead(float $now, JoinHandler $handler) : bool{
		$this->readInto(self::MAX_HEAD_SIZE + 1);

		if($this->state === HttpConnectionState::READING_HEAD && strlen($this->input) >= 3 && $this->input[0] === self::TLS_HANDSHAKE_RECORD && $this->input[1] === self::TLS_MAJOR_VERSION){
			throw new HttpException(HttpStatus::BAD_REQUEST, "Client initiated a TLS handshake on a plaintext endpoint; TLS certificate is not configured");
		}
		if($now > $this->deadline){
			throw new HttpException(HttpStatus::REQUEST_TIMEOUT, "Timed out reading the request head after " . strlen($this->input) . " bytes: " . self::describeBytes($this->input));
		}

		$headEnd = self::findHeadEnd($this->input);
		if($headEnd === null){
			if(strlen($this->input) > self::MAX_HEAD_SIZE){
				throw new HttpException(HttpStatus::REQUEST_HEADER_FIELDS_TOO_LARGE, "Request head is too large");
			}
			$this->closeIfPeerGone();

			return false;
		}

		[$end, $terminatorLength] = $headEnd;
		if($end > self::MAX_HEAD_SIZE){
			throw new HttpException(HttpStatus::REQUEST_HEADER_FIELDS_TOO_LARGE, "Request head is too large");
		}

		$this->request = HttpRequest::parse(substr($this->input, 0, $end));
		$this->bodyOffset = $end + $terminatorLength;

		if($this->state === HttpConnectionState::READING_HEAD_TO_REDIRECT){
			/* 308 has the client repeat the request, method and body included, against the address it is given. */
			$this->respond(new HttpResponse(
				HttpStatus::PERMANENT_REDIRECT,
				headers: [HttpResponse::HEADER_LOCATION => "https://" . ($this->request->getHeader(HttpRequest::HEADER_HOST) ?? "") . $this->request->target]
			), $now);

			return false;
		}

		/* Turns away what cannot be served before any of its body is read. */
		$handler->route($this->request);
		$expectsContinue = strcasecmp(trim($this->request->getHeader(HttpRequest::HEADER_EXPECT) ?? ""), "100-continue") === 0;
		$this->contentLength = $this->request->method === HttpRequest::METHOD_POST ? $this->request->getContentLength(self::MAX_BODY_SIZE) : 0;
		$this->transitionTo(HttpConnectionState::READING_BODY, $this->requestStart + self::BODY_TIMEOUT);

		if($expectsContinue && strlen($this->input) - $this->bodyOffset < $this->contentLength){
			/* A client that asks first holds its body back until told to send it. */
			$written = @fwrite($this->stream, self::CONTINUE_RESPONSE);
			if($written !== false && $written !== 0 && $written !== strlen(self::CONTINUE_RESPONSE)){
				/* Half of it on the wire would corrupt whatever response follows. */
				$this->state = HttpConnectionState::DONE;

				return false;
			}
		}

		return true;
	}

	/**
	 * @throws HttpException
	 */
	private function readBody(float $now, JoinHandler $handler) : void{
		$request = $this->request;
		if($request === null){
			throw new \LogicException("Reading a body before the request head was read");
		}

		$this->readInto($this->bodyOffset + $this->contentLength);
		if($now > $this->deadline){
			throw new HttpException(HttpStatus::REQUEST_TIMEOUT, "Timed out reading the request body");
		}
		if(strlen($this->input) - $this->bodyOffset < $this->contentLength){
			$this->closeIfPeerGone();

			return;
		}

		$result = $handler->handle($request, substr($this->input, $this->bodyOffset, $this->contentLength), $this->peerAddress);
		if($result === null){
			$this->state = HttpConnectionState::DONE;
		}elseif($result instanceof HttpResponse){
			$this->respond($result, $now);
		}else{
			/* The negotiator bounds how long a negotiation takes, so there is nothing to time out here. */
			$this->negotiation = $result;
			$this->transitionTo(HttpConnectionState::NEGOTIATING, INF);
		}
	}

	private function pollNegotiation(float $now, JoinHandler $handler) : void{
		$response = $this->negotiation !== null ? $handler->respondTo($this->negotiation) : null;
		if($response !== null){
			$this->respond($response, $now);
		}
	}

	private function write(float $now) : void{
		$this->flush();
		if($this->state === HttpConnectionState::WRITING && $now > $this->deadline){
			$this->logger->debug($this->getPeerName() . ": timed out sending the response");
			$this->state = HttpConnectionState::DONE;
		}
	}

	private function respond(HttpResponse $response, float $now) : void{
		if($this->state === HttpConnectionState::WRITING || $this->state === HttpConnectionState::DONE){
			return;
		}

		$this->logger->debug(
			$this->getPeerName() . ": " .
			($this->request === null ? "<unparsed request>" : $this->request->method . " " . $this->request->getPath()) .
			" -> " . $response->status->value . " " . $response->status->getReasonPhrase()
		);

		$this->output = $response->encode(omitBody: $this->request?->method === HttpRequest::METHOD_HEAD);
		$this->transitionTo(HttpConnectionState::WRITING, $now + self::WRITE_TIMEOUT);
		$this->flush();
	}

	private function flush() : void{
		while($this->output !== ""){
			$written = @fwrite($this->stream, $this->output);
			if($written === false || $written === 0){
				if(feof($this->stream)){
					$this->state = HttpConnectionState::DONE;
				}

				return;
			}

			$this->output = substr($this->output, $written);
		}

		$this->state = HttpConnectionState::DONE;
	}

	private function readInto(int $limit) : void{
		while(($wanted = $limit - strlen($this->input)) > 0){
			$chunk = @fread($this->stream, min($wanted, self::READ_CHUNK_SIZE));
			if($chunk === false || $chunk === ""){
				return;
			}
			$this->input .= $chunk;
		}
	}

	private function closeIfPeerGone() : void{
		if(!feof($this->stream)){
			return;
		}

		if($this->input !== ""){
			$this->logger->debug($this->getPeerName() . ": closed the connection after " . strlen($this->input) . " bytes of a request");
		}
		$this->state = HttpConnectionState::DONE;
	}

	/**
	 * Closes the stream, failing the negotiation if its answer never reached the client.
	 */
	public function close() : void{
		if($this->negotiation !== null && ($this->negotiation->getAnswer() === null || $this->output !== "")){
			$this->negotiation->fail("Signaling connection closed before the answer was delivered");
		}
		$this->state = HttpConnectionState::DONE;

		if(is_resource($this->stream)){
			/* Closing with unread data makes the kernel reset the connection, which can cost the client the response it was just sent. */
			$drained = 0;
			while($drained < self::MAX_DRAIN_ON_CLOSE){
				$chunk = @fread($this->stream, self::READ_CHUNK_SIZE);
				if($chunk === false || $chunk === ""){
					break;
				}
				$drained += strlen($chunk);
			}

			fclose($this->stream);
		}
	}

	/**
	 * Finds the blank line that ends the request head.
	 *
	 * @return array{int, int}|null offset of the terminator and its length
	 */
	private static function findHeadEnd(string $input) : ?array{
		$crlf = strpos($input, "\r\n\r\n");
		$lf = strpos($input, "\n\n");

		if($crlf !== false && ($lf === false || $crlf < $lf)){
			return [$crlf, 4];
		}
		if($lf !== false){
			return [$lf, 2];
		}

		return null;
	}

	private static function describeBytes(string $data, int $limit = 96) : string{
		$sample = substr($data, 0, $limit);

		$hex = implode(" ", str_split(bin2hex(substr($sample, 0, 16)), 2));
		$text = preg_replace('/[^\x20-\x7e]/', ".", $sample) ?? "";

		return "hex[$hex] text[$text]" . (strlen($data) > $limit ? "..." : "");
	}
}
