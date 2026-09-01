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

namespace pocketmine\network\mcpe\protocol\types;

final class PersonaPieceTintColor{

	public const PIECE_TYPE_PERSONA_MOUTH = "mouth";
	public const PIECE_TYPE_PERSONA_EYES = "eyes";
	public const PIECE_TYPE_PERSONA_HAIR = "hair";

	private const PIECE_TYPE_NAMES = [
		"skeleton" => 0,
		"body" => 1,
		"skin" => 2,
		"bottom" => 3,
		"feet" => 4,
		"dress" => 5,
		"top" => 6,
		"high_pants" => 7,
		"hands" => 8,
		"hand" => 8,
		"outerwear" => 9,
		"facial_hair" => 10,
		"mouth" => 11,
		"eyes" => 12,
		"hair" => 13,
		"hood" => 14,
		"back" => 15,
		"face_accessory" => 16,
		"head" => 17,
		"legs" => 18,
		"left_leg" => 19,
		"right_leg" => 20,
		"arms" => 21,
		"left_arm" => 22,
		"right_arm" => 23,
		"capes" => 24,
		"classic_skin" => 25,
		"emote" => 26
	];

	private string $serializeName;
	/** @var int[] */
	private array $colors;

	/**
	 * @param int[] $colors
	 */
	public function __construct(int|string $pieceType, array $colors){
		$this->serializeName = self::resolveSerializeName($pieceType);
		$this->colors = $colors;
	}

	public function getPieceType() : string{
		return $this->serializeName;
	}

	private static function resolveSerializeName(int|string $pieceType) : string{
		if(is_string($pieceType)){
			$name = strtolower($pieceType);
			if(str_starts_with($name, "persona_")){
				$name = substr($name, strlen("persona_"));
			}

			if(isset(self::PIECE_TYPE_NAMES[$name])){
				return $name;
			}

			throw new \InvalidArgumentException("Unknown piece type \"$pieceType\"");
		}

		$name = array_search($pieceType, self::PIECE_TYPE_NAMES, true);
		if($name !== false){
			return $name;
		}

		throw new \InvalidArgumentException("Unknown piece type ordinal \"$pieceType\"");
	}

	/**
	 * @return int[]
	 */
	public function getColors() : array{
		return $this->colors;
	}
}
