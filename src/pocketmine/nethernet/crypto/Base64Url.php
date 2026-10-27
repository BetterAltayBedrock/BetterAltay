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

use function base64_decode;
use function base64_encode;
use function rtrim;
use function str_repeat;
use function strlen;
use function strtr;

/**
 * @internal
 */
final class Base64Url{

	private function __construct(){}

	public static function encode(string $data) : string{
		return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
	}

	/**
	 * @throws CryptoException
	 */
	public static function decode(string $data) : string{
		$remainder = strlen($data) % 4;
		if($remainder !== 0){
			$data .= str_repeat("=", 4 - $remainder);
		}

		$decoded = base64_decode(strtr($data, "-_", "+/"), true);
		if($decoded === false){
			throw new CryptoException("Value is not valid base64url");
		}

		return $decoded;
	}
}
