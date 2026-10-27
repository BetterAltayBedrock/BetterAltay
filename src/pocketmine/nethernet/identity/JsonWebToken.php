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

use pocketmine\nethernet\crypto\Base64Url;
use pocketmine\nethernet\crypto\CryptoException;
use function count;
use function explode;
use function is_float;
use function is_int;
use function is_string;

/**
 * A JWT (RFC 7519) carried in a NetherNet identity assertion: either a client's GameServerToken issued by
 * Minecraft's auth services, or a host's self-signed token. Parsing does not verify the signature.
 */
final class JsonWebToken{

	/** The key the token's holder proves possession of. */
	public const CLAIM_PUBLIC_KEY = "cpk";
	public const CLAIM_EXPIRY = "exp";
	public const CLAIM_NOT_BEFORE = "nbf";
	public const CLAIM_ISSUED_AT = "iat";

	public const HEADER_ALGORITHM = "alg";
	public const HEADER_PUBLIC_KEY = "x5u";

	public const DEFAULT_LEEWAY_SECONDS = 60;

	/**
	 * @phpstan-param array<mixed> $header
	 * @phpstan-param array<mixed> $claims
	 */
	private function __construct(
		private readonly string $compact,
		private readonly array $header,
		private readonly array $claims,
		private readonly PublicKey $publicKey,
		private readonly string $signingInput,
		private readonly string $signature
	){}

	/**
	 * @throws IdentityException
	 */
	public static function parse(string $compact) : self{
		$parts = explode(".", $compact, limit: 4);
		if(count($parts) !== 3){
			throw new IdentityException("Expected 3 parts in JWT, got " . count($parts));
		}

		try{
			$header = Json::decodeObject(Base64Url::decode($parts[0]), "token header");
			$claims = Json::decodeObject(Base64Url::decode($parts[1]), "token claims");
			$signature = Base64Url::decode($parts[2]);
		}catch(CryptoException $e){
			throw new IdentityException("Token is not valid base64url", 0, $e);
		}

		if(!isset($claims[self::CLAIM_PUBLIC_KEY])){
			throw new IdentityException("Token has no " . self::CLAIM_PUBLIC_KEY . " claim");
		}

		return new self($compact, $header, $claims, PublicKey::fromClaim($claims[self::CLAIM_PUBLIC_KEY]), $parts[0] . "." . $parts[1], $signature);
	}

	/**
	 * Builds a token and signs it with the given identity.
	 *
	 * @phpstan-param array<string, mixed> $header
	 * @phpstan-param array<string, mixed> $claims
	 *
	 * @throws IdentityException
	 */
	public static function create(array $header, array $claims, ServerIdentity $signer) : self{
		$header[self::HEADER_ALGORITHM] = JsonWebSignature::ALGORITHM;
		$signingInput = Base64Url::encode(Json::encode($header)) . "." . Base64Url::encode(Json::encode($claims));

		return self::parse($signingInput . "." . Base64Url::encode($signer->sign($signingInput)));
	}

	public function toString() : string{ return $this->compact; }

	/**
	 * @phpstan-return array<mixed>
	 */
	public function getHeader() : array{ return $this->header; }

	/**
	 * @phpstan-return array<mixed>
	 */
	public function getClaims() : array{ return $this->claims; }

	public function getAlgorithm() : ?string{
		$algorithm = $this->header[self::HEADER_ALGORITHM] ?? null;

		return is_string($algorithm) ? $algorithm : null;
	}

	/**
	 * Returns the key named by the token's `cpk` claim. Whether the token can be trusted to name it is a separate question.
	 */
	public function getPublicKey() : PublicKey{ return $this->publicKey; }

	/**
	 * @throws IdentityException if the token is not signed with ES384
	 */
	public function isSignedBy(PublicKey $key) : bool{
		if($this->getAlgorithm() !== JsonWebSignature::ALGORITHM){
			throw new IdentityException("Token must be signed with " . JsonWebSignature::ALGORITHM);
		}

		return $key->verify($this->signingInput, $this->signature);
	}

	/**
	 * @param int $now Current Unix time
	 *
	 * @throws IdentityException if the token has expired, is not valid yet, or claims to be issued in the future
	 */
	public function checkTimestamps(int $now, int $leewaySeconds = self::DEFAULT_LEEWAY_SECONDS) : void{
		$notBefore = $this->readTimestamp(self::CLAIM_NOT_BEFORE);
		if($notBefore !== null && $now + $leewaySeconds < $notBefore){
			throw new IdentityException("Token is not valid yet");
		}

		$issuedAt = $this->readTimestamp(self::CLAIM_ISSUED_AT);
		if($issuedAt !== null && $now + $leewaySeconds < $issuedAt){
			throw new IdentityException("Token was issued in the future");
		}

		$expiry = $this->readTimestamp(self::CLAIM_EXPIRY);
		if($expiry !== null && $now - $leewaySeconds >= $expiry){
			throw new IdentityException("Token has expired");
		}
	}

	/**
	 * @throws IdentityException
	 */
	private function readTimestamp(string $claim) : ?int{
		$value = $this->claims[$claim] ?? null;
		if($value === null){
			return null;
		}
		if(is_int($value)){
			return $value;
		}
		if(is_float($value)){
			return (int) $value;
		}

		throw new IdentityException("Token claim $claim is not a number");
	}
}
