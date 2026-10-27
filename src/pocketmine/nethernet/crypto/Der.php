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

use function chr;
use function ltrim;
use function pack;
use function strlen;

/**
 * @internal
 */
final class Der{

	private const MAX_LENGTH = 0xffffffff;

	private function __construct(){
		//NOOP
	}

	public static function encode(int $tag, string $body) : string{
		return chr($tag) . self::length(strlen($body)) . $body;
	}

	public static function sequence(string $body) : string{
		return self::encode(0x30, $body);
	}

	/**
	 * @param string $body Two's complement big-endian value, exactly as it is to appear
	 */
	public static function integer(string $body) : string{
		return self::encode(0x02, $body);
	}

	/**
	 * Encodes an unsigned big-endian value as the shortest INTEGER that stays positive.
	 */
	public static function unsignedInteger(string $value) : string{
		$value = ltrim($value, "\x00");
		if($value === "" || ($value[0] & "\x80") !== "\x00"){
			$value = "\x00" . $value;
		}

		return self::integer($value);
	}

	public static function octetString(string $body) : string{
		return self::encode(0x04, $body);
	}

	/**
	 * Encodes a byte-aligned BIT STRING with zero unused bits.
	 */
	public static function bitString(string $body) : string{
		return self::encode(0x03, "\x00" . $body);
	}

	/**
	 * Encodes a context-specific constructed tag (e.g. for optional SEC1 structures).
	 */
	public static function tagged(int $number, string $body) : string{
		return self::encode(0xa0 | $number, $body);
	}

	private static function length(int $length) : string{
		if($length <= 0x7f){
			return chr($length);
		}
		if($length > self::MAX_LENGTH){
			throw new \InvalidArgumentException("DER body of $length bytes is too long to encode");
		}

		$bytes = ltrim(pack("N", $length), "\x00");

		return chr(0x80 | strlen($bytes)) . $bytes;
	}
}
