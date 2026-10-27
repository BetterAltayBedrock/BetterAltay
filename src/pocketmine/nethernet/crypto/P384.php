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

use function is_array;
use function openssl_pkey_get_details;
use function str_pad;
use function strlen;
use const STR_PAD_LEFT;

/**
 * @internal
 * Facts about the one curve NetherNet identities use.
 */
final class P384{

	public const COORDINATE_SIZE = 48;
	public const OPENSSL_CURVE_NAME = "secp384r1";
	public const JWK_CURVE_NAME = "P-384";

	/** AlgorithmIdentifier contents: id-ecPublicKey (1.2.840.10045.2.1), then secp384r1 (1.3.132.0.34). */
	public const ALGORITHM_IDENTIFIER = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x05\x2b\x81\x04\x00\x22";

	private function __construct(){
		//NOOP
	}

	/**
	 * @throws CryptoException if the key is not on this curve
	 */
	public static function checkKey(\OpenSSLAsymmetricKey $key) : void{
		$details = openssl_pkey_get_details($key);
		$curve = $details !== false && isset($details["ec"]) && is_array($details["ec"]) ? ($details["ec"]["curve_name"] ?? null) : null;
		if($curve !== self::OPENSSL_CURVE_NAME){
			throw new CryptoException("Key must be an EC key on curve " . self::OPENSSL_CURVE_NAME);
		}
	}

	/**
	 * Restores a coordinate OpenSSL stripped of leading zeros to its full width.
	 *
	 * @throws CryptoException
	 */
	public static function padCoordinate(string $value) : string{
		if(strlen($value) > self::COORDINATE_SIZE){
			throw new CryptoException("Curve component of " . strlen($value) . " bytes exceeds the " . self::COORDINATE_SIZE . " byte coordinate size");
		}

		return str_pad($value, self::COORDINATE_SIZE, "\x00", STR_PAD_LEFT);
	}

	public static function encodeSubjectPublicKeyInfo(string $x, string $y) : string{
		return Der::sequence(Der::sequence(self::ALGORITHM_IDENTIFIER) . Der::bitString("\x04" . $x . $y));
	}
}
