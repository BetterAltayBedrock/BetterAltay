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
use pocketmine\nethernet\crypto\Es384;
use pocketmine\nethernet\crypto\OpenSsl;
use pocketmine\nethernet\crypto\P384;
use function base64_decode;
use function base64_encode;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;
use function openssl_pkey_get_details;
use function openssl_pkey_get_public;
use function preg_match;
use function sprintf;
use function str_replace;
use function strlen;

/**
 * A P-384 public key.
 */
final class PublicKey{

	private function __construct(
		private readonly \OpenSSLAsymmetricKey $key,
		private readonly string $der
	){}

	/**
	 * Reads a `cpk` claim, which is either a base64 SubjectPublicKeyInfo or a JWK.
	 *
	 * @throws IdentityException
	 */
	public static function fromClaim(mixed $value) : self{
		if(is_string($value)){
			return self::fromBase64Der($value);
		}
		if(is_array($value)){
			return self::fromJwk($value);
		}

		throw new IdentityException("Public key claim must be a base64 string or a JWK object");
	}

	/**
	 * @throws IdentityException
	 */
	public static function fromBase64Der(string $base64) : self{
		$der = base64_decode($base64, true);
		if($der === false || $der === ""){
			throw new IdentityException("Public key is not valid base64");
		}

		return self::fromDer($der);
	}

	/**
	 * @phpstan-param array<array-key, mixed> $jwk
	 *
	 * @throws IdentityException
	 */
	public static function fromJwk(array $jwk) : self{
		if(($jwk["kty"] ?? null) !== "EC"){
			throw new IdentityException("Public key JWK must have kty EC");
		}
		if(($jwk["crv"] ?? null) !== P384::JWK_CURVE_NAME){
			throw new IdentityException("Public key JWK must use curve " . P384::JWK_CURVE_NAME);
		}

		$x = $jwk["x"] ?? null;
		$y = $jwk["y"] ?? null;
		if(!is_string($x) || !is_string($y)){
			throw new IdentityException("Public key JWK is missing its coordinates");
		}

		try{
			$xBytes = Base64Url::decode($x);
			$yBytes = Base64Url::decode($y);
		}catch(CryptoException $e){
			throw new IdentityException("Public key JWK coordinates are not valid base64url", 0, $e);
		}
		if(strlen($xBytes) !== P384::COORDINATE_SIZE || strlen($yBytes) !== P384::COORDINATE_SIZE){
			throw new IdentityException("Public key JWK coordinates must be " . P384::COORDINATE_SIZE . " bytes each");
		}

		return self::fromDer(P384::encodeSubjectPublicKeyInfo($xBytes, $yBytes));
	}

	/**
	 * @param string $der SubjectPublicKeyInfo
	 *
	 * @throws IdentityException
	 */
	public static function fromDer(string $der) : self{
		$key = openssl_pkey_get_public(sprintf("-----BEGIN PUBLIC KEY-----\n%s\n-----END PUBLIC KEY-----\n", base64_encode($der)));
		if($key === false){
			throw new IdentityException("OpenSSL rejected the public key: " . OpenSsl::lastError());
		}

		try{
			P384::checkKey($key);
		}catch(CryptoException $e){
			throw new IdentityException($e->getMessage(), 0, $e);
		}

		return new self($key, $der);
	}

	/**
	 * @throws IdentityException
	 */
	public static function fromOpenSslKey(\OpenSSLAsymmetricKey $key) : self{
		$details = openssl_pkey_get_details($key);
		if($details === false || !isset($details["key"]) || !is_string($details["key"])){
			throw new IdentityException("Failed to extract public key from OpenSSL key: " . OpenSsl::lastError());
		}
		if(preg_match("@^-----BEGIN[A-Z\d ]+PUBLIC KEY-----\n([A-Za-z\d+/\n=]+)\n-----END[A-Z\d ]+PUBLIC KEY-----\n$@", $details["key"], $matches) !== 1){
			throw new IdentityException("OpenSSL produced a public key in an unexpected format");
		}
		$der = base64_decode(str_replace("\n", "", $matches[1]), true);
		if($der === false){
			throw new IdentityException("OpenSSL produced a public key that is not valid base64");
		}

		return self::fromDer($der);
	}

	/**
	 * Returns whether an ES384 signature over the input was made by this key.
	 *
	 * @throws IdentityException if the signature is not in a form that can be checked
	 */
	public function verify(string $input, string $signature) : bool{
		try{
			return Es384::verify($input, $signature, $this->key);
		}catch(CryptoException $e){
			throw new IdentityException($e->getMessage(), 0, $e);
		}
	}

	public function toBase64Der() : string{
		return base64_encode($this->der);
	}

	/**
	 * Returns the raw SHA-256 digest of the key's SubjectPublicKeyInfo.
	 */
	public function getDigest() : string{
		return hash("sha256", $this->der, binary: true);
	}

	public function equals(self $other) : bool{
		return hash_equals($this->der, $other->der);
	}
}
