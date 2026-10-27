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
use function count;
use function explode;

/**
 * Detached ES384 JWS signatures (RFC 7515 Appendix F).
 */
final class JsonWebSignature{

	public const ALGORITHM = Es384::ALGORITHM;

	private const PROTECTED_HEADER = '{"alg":"' . self::ALGORITHM . '"}';

	private function __construct(){
		//NOOP
	}

	/**
	 * @throws IdentityException
	 */
	public static function signDetached(string $payload, ServerIdentity $signer) : string{
		$header = Base64Url::encode(self::PROTECTED_HEADER);

		return $header . ".." . Base64Url::encode($signer->sign($header . "." . Base64Url::encode($payload)));
	}

	/**
	 * @throws IdentityException if the signature is not a detached ES384 JWS
	 */
	public static function verifyDetached(string $detached, string $payload, PublicKey $publicKey) : bool{
		$parts = explode(".", $detached, limit: 4);
		if(count($parts) !== 3){
			throw new IdentityException("Detached signature must consist of exactly three dot-separated parts, got " . count($parts));
		}
		if($parts[1] !== ""){
			throw new IdentityException("Detached signature must not carry a payload");
		}

		try{
			$header = Json::decodeObject(Base64Url::decode($parts[0]), "detached signature header");
			$signature = Base64Url::decode($parts[2]);
		}catch(CryptoException $e){
			throw new IdentityException("Detached signature is not valid base64url", 0, $e);
		}
		if(($header["alg"] ?? null) !== self::ALGORITHM){
			throw new IdentityException("Detached signature must use " . self::ALGORITHM);
		}

		return $publicKey->verify($parts[0] . "." . Base64Url::encode($payload), $signature);
	}
}
