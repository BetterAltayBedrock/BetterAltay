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

use pocketmine\utils\UUID;

final class PersonaSkinPiece{
	public const PIECE_TYPE_PERSONA_UNKNOWN = 0;
	public const PIECE_TYPE_PERSONA_SKELETON = 1;
	public const PIECE_TYPE_PERSONA_BODY = 2;
	public const PIECE_TYPE_PERSONA_SKIN = 3;
	public const PIECE_TYPE_PERSONA_BOTTOM = 4;
	public const PIECE_TYPE_PERSONA_FEET = 5;
	public const PIECE_TYPE_PERSONA_DRESS = 6;
	public const PIECE_TYPE_PERSONA_TOP = 7;
	public const PIECE_TYPE_PERSONA_HIGH_PANTS = 8;
	public const PIECE_TYPE_PERSONA_HANDS = 9;
	public const PIECE_TYPE_PERSONA_OUTERWEAR = 10;
	public const PIECE_TYPE_PERSONA_FACIAL_HAIR = 11;
	public const PIECE_TYPE_PERSONA_MOUTH = 12;
	public const PIECE_TYPE_PERSONA_EYES = 13;
	public const PIECE_TYPE_PERSONA_HAIR = 14;
	public const PIECE_TYPE_PERSONA_HOOD = 15;
	public const PIECE_TYPE_PERSONA_BACK = 16;
	public const PIECE_TYPE_PERSONA_FACE_ACCESSORY = 17;
	public const PIECE_TYPE_PERSONA_HEAD = 18;
	public const PIECE_TYPE_PERSONA_LEGS = 19;
	public const PIECE_TYPE_PERSONA_LEFT_LEG = 20;
	public const PIECE_TYPE_PERSONA_RIGHT_LEG = 21;
	public const PIECE_TYPE_PERSONA_ARMS = 22;
	public const PIECE_TYPE_PERSONA_LEFT_ARM = 23;
	public const PIECE_TYPE_PERSONA_RIGHT_ARM = 24;
	public const PIECE_TYPE_PERSONA_CAPES = 25;
	public const PIECE_TYPE_PERSONA_CLASSIC_SKIN = 26;
	public const PIECE_TYPE_PERSONA_EMOTE = 27;
	public const PIECE_TYPE_PERSONA_UNSUPPORTED = 28;


	public function __construct(
		private string $pieceId,
		private int $pieceType,
		private UUID $packId,
		private bool $isDefaultPiece,
		private string $productId){
	}

	public static function pieceTypeToId(int|string $pieceType) : int{
		if(is_int($pieceType)){
			return $pieceType;
		}

		$name = strtolower($pieceType);
		if(str_starts_with($name, "persona_")){
			$name = substr($name, strlen("persona_"));
		}

		$byName = [
			"unknown" => 0,
			"skeleton" => 1,
			"body" => 2,
			"skin" => 3,
			"bottom" => 4,
			"feet" => 5,
			"dress" => 6,
			"top" => 7,
			"high_pants" => 8,
			"hands" => 9,
			"hand" => 9,
			"outerwear" => 10,
			"facialhair" => 11,
			"facial_hair" => 11,
			"mouth" => 12,
			"eyes" => 13,
			"hair" => 14,
			"hood" => 15,
			"back" => 16,
			"faceaccessory" => 17,
			"face_accessory" => 17,
			"head" => 18,
			"legs" => 19,
			"leftleg" => 20,
			"left_leg" => 20,
			"rightleg" => 21,
			"right_leg" => 21,
			"arms" => 22,
			"leftarm" => 23,
			"left_arm" => 23,
			"rightarm" => 24,
			"right_arm" => 24,
			"capes" => 25,
			"classicskin" => 26,
			"classic_skin" => 26,
			"emote" => 27,
			"unsupported" => 28,
		];

		if(isset($byName[$name])){
			return $byName[$name];
		}

		throw new \InvalidArgumentException("Unknown persona piece type \"$pieceType\"");
	}

	public function getPieceId() : string{
		return $this->pieceId;
	}

	public function getPieceType() : int{
		return $this->pieceType;
	}

	public function getPackId() : UUID{
		return $this->packId;
	}

	public function isDefaultPiece() : bool{
		return $this->isDefaultPiece;
	}

	public function getProductId() : string{
		return $this->productId;
	}
}