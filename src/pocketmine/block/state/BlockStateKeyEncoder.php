<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block\state;

use function count;
use function hash;
use function is_bool;
use function is_int;
use function is_string;
use function ksort;
use const SORT_STRING;

final class BlockStateKeyEncoder{

	private function __construct(){
	}

	/**
	 * @param array<string, int|bool|string> $states
	 */
	public static function encode(string $id, array $states) : string{
		if(count($states) === 0){
			return $id;
		}

		$encoded = [];
		foreach($states as $name => $value){
			if(is_bool($value)){
				$encoded[$name] = "i" . (int) $value;
			}elseif(is_int($value)){
				$encoded[$name] = "i" . $value;
			}elseif(is_string($value)){
				$encoded[$name] = "s" . $value;
			}else{
				$encoded[$name] = "f" . $value;
			}
		}

		ksort($encoded, SORT_STRING);

		$key = $id;
		foreach($encoded as $name => $value){
			$key .= "\x00" . $name . "\x00" . $value;
		}
		return $key;
	}

	/**
	 * @param array<string, int|bool|string> $states
	 */
	public static function hash(string $id, array $states) : string{
		return hash("fnv1a64", self::encode($id, $states));
	}

	public static function hashStateData(string $id, StateData $states) : string{
		return self::hash($id, $states->getAll());
	}
}
