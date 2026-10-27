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

use function array_is_list;
use function is_array;
use function json_decode;
use function json_encode;
use function ucfirst;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @internal
 */
final class Json{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return mixed[]
	 * @phpstan-return array<mixed>
	 *
	 * @throws IdentityException
	 */
	public static function decodeObject(string $json, string $what) : array{
		try{
			$decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			throw new IdentityException("Malformed $what: " . $e->getMessage(), 0, $e);
		}
		if(!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))){
			throw new IdentityException(ucfirst($what) . " is not a JSON object");
		}

		return $decoded;
	}

	/**
	 * @phpstan-param array<string, mixed> $value
	 *
	 * @throws IdentityException
	 */
	public static function encode(array $value) : string{
		try{
			return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		}catch(\JsonException $e){
			throw new IdentityException("Could not encode JSON: " . $e->getMessage(), 0, $e);
		}
	}
}
