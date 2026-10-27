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

use function bin2hex;
use function ltrim;
use function ord;
use function str_pad;
use function str_split;
use function strlen;
use function substr;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final class EcdsaSignature{

	private const ASN1_INTEGER_TAG = "\x02";
	private const ASN1_SEQUENCE_TAG = "\x30";
	private const ASN1_LONG_FORM_FLAG = 0x80;

	private function __construct(){}

	/**
	 * Converts an ASN.1 DER-encoded ECDSA signature to IEEE P1363 / JOSE format (r || s).
	 *
	 * @throws CryptoException
	 */
	public static function derToJose(string $der) : string{
		$coordinateSize = P384::COORDINATE_SIZE;
		if(strlen($der) < 2 || $der[0] !== self::ASN1_SEQUENCE_TAG){
			throw new CryptoException("Signature does not start with an ASN.1 SEQUENCE tag");
		}

		$length = ord($der[1]);
		if(($length & self::ASN1_LONG_FORM_FLAG) !== 0){
			throw new CryptoException("Long-form DER length is not permitted for ECDSA signatures");
		}
		if(strlen($der) !== $length + 2){
			throw new CryptoException("Signature length byte $length does not match actual length " . (strlen($der) - 2));
		}

		$offset = 2;
		$r = self::readInteger($der, $offset, $coordinateSize);
		$s = self::readInteger($der, $offset, $coordinateSize);
		if($offset !== strlen($der)){
			throw new CryptoException("Signature has trailing data after its two integers");
		}

		return $r . $s;
	}

	/**
	 * @throws CryptoException
	 */
	private static function readInteger(string $der, int &$offset, int $coordinateSize) : string{
		if($offset + 2 > strlen($der)){
			throw new CryptoException("Signature ended before an expected integer");
		}

		$tag = $der[$offset];
		if($tag !== self::ASN1_INTEGER_TAG){
			throw new CryptoException("Expected an ASN.1 INTEGER tag, got " . bin2hex($tag));
		}

		$length = ord($der[$offset + 1]);
		if(($length & self::ASN1_LONG_FORM_FLAG) !== 0){
			throw new CryptoException("Integer uses a long-form DER length");
		}
		// An extra byte is allowed for the leading zero that prevents positive integers from having the sign bit set
		if($length < 1 || $length > $coordinateSize + 1){
			throw new CryptoException("Integer length $length does not fit a $coordinateSize byte coordinate");
		}

		$offset += 2;
		if($offset + $length > strlen($der)){
			throw new CryptoException("Signature ended in the middle of an integer");
		}

		$value = ltrim(substr($der, $offset, $length), "\x00");
		$offset += $length;

		if(strlen($value) > $coordinateSize){
			throw new CryptoException("Integer length exceeds coordinate size of $coordinateSize bytes");
		}

		return str_pad($value, $coordinateSize, "\x00", STR_PAD_LEFT);
	}

	/**
	 * Converts an IEEE P1363 / JOSE formatted signature (r || s) to ASN.1 DER format.
	 *
	 * @throws CryptoException
	 */
	public static function joseToDer(string $jose) : string{
		$coordinateSize = P384::COORDINATE_SIZE;
		if(strlen($jose) !== $coordinateSize * 2){
			throw new CryptoException("Signature must be " . ($coordinateSize * 2) . " bytes, got " . strlen($jose));
		}

		[$r, $s] = str_split($jose, $coordinateSize);
		return Der::sequence(Der::unsignedInteger($r) . Der::unsignedInteger($s));
	}
}
