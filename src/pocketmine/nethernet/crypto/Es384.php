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

namespace pocketmine\nethernet\crypto;

use function openssl_sign;
use function openssl_verify;
use const OPENSSL_ALGO_SHA384;

/**
 * @internal
 * ECDSA over P-384 with SHA-384, with signatures in JOSE form (r || s).
 */
final class Es384{

	public const ALGORITHM = "ES384";

	private function __construct(){
		//NOOP
	}

	/**
	 * @throws CryptoException
	 */
	public static function sign(string $input, \OpenSSLAsymmetricKey $privateKey) : string{
		$der = "";
		if(!openssl_sign($input, $der, $privateKey, OPENSSL_ALGO_SHA384)){
			throw new CryptoException("OpenSSL failed to sign: " . OpenSsl::lastError());
		}

		return EcdsaSignature::derToJose($der);
	}

	/**
	 * @throws CryptoException
	 */
	public static function verify(string $input, string $signature, \OpenSSLAsymmetricKey $publicKey) : bool{
		return match(openssl_verify($input, EcdsaSignature::joseToDer($signature), $publicKey, OPENSSL_ALGO_SHA384)){
			1 => true,
			0 => false,
			default => throw new CryptoException("OpenSSL failed to verify: " . OpenSsl::lastError())
		};
	}
}
