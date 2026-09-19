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

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\UUID;

class SkinData{

	public const ARM_SIZE_SLIM = 0;
	public const ARM_SIZE_WIDE = 1;

	public const TRUSTED_SKIN_FLAG_UNSET = "unset";
	public const TRUSTED_SKIN_FLAG_FALSE = "false";
	public const TRUSTED_SKIN_FLAG_TRUE = "true";

	/** @var string */
	private $skinId;
	/** @var string */
	private $playFabId;
	/** @var string */
	private $resourcePatch;
	/** @var SkinImage */
	private $skinImage;
	/** @var SkinAnimation[] */
	private $animations;
	/** @var SkinImage */
	private $capeImage;
	/** @var string */
	private $geometryData;
	private string $geometryDataEngineVersion;
	/** @var string */
	private $animationData;
	/** @var string */
	private $capeId;
	/** @var string */
	private $fullSkinId;
	/** @var int */
	private $armSize;
	/** @var int */
	private $skinColor;
	/** @var PersonaSkinPiece[] */
	private $personaPieces;
	/** @var PersonaPieceTintColor[] */
	private $pieceTintColors;
	/** @var bool */
	private $isVerified;
	/** @var bool */
	private $persona;
	/** @var bool */
	private $premium;
	/** @var bool */
	private $personaCapeOnClassic;
	/** @var bool */
	private $isPrimaryUser;
	private bool $override;
	private string $trustedSkinFlag;
	private string $profileHash;

	/**
	 * @param SkinAnimation[]         $animations
	 * @param PersonaSkinPiece[]      $personaPieces
	 * @param PersonaPieceTintColor[] $pieceTintColors
	 */
	public function __construct(string $skinId,
		string $playFabId,
		string $resourcePatch,
		SkinImage $skinImage,
		array $animations = [],
		SkinImage $capeImage = null,
		string $geometryData = "{}",
		string $geometryDataEngineVersion = ProtocolInfo::MINECRAFT_VERSION_NETWORK,
		string $animationData = "",
		string $capeId = "",
		?string $fullSkinId = null,
		int $armSize = self::ARM_SIZE_WIDE,
		int $skinColor = 0,
		array $personaPieces = [],
		array $pieceTintColors = [],
		bool $isVerified = true,
		bool $premium = false,
		bool $persona = false,
		bool $personaCapeOnClassic = false,
		bool $isPrimaryUser = true,
		bool $override = true,
		string $trustedSkinFlag = self::TRUSTED_SKIN_FLAG_TRUE,
		string $profileHash = ""
	){
		$this->skinId = $skinId;
		$this->playFabId = $playFabId;
		$this->resourcePatch = $resourcePatch;
		$this->skinImage = $skinImage;
		$this->animations = $animations;
		$this->capeImage = $capeImage ?? new SkinImage(0, 0, "");
		$this->geometryData = $geometryData;
		$this->geometryDataEngineVersion = $geometryDataEngineVersion;
		$this->animationData = $animationData;
		$this->capeId = $capeId;
		//this has to be unique or the client will do stupid things
		$this->fullSkinId = $fullSkinId ?? UUID::fromRandom()->toString();
		$this->armSize = $armSize;
		$this->skinColor = $skinColor;
		$this->personaPieces = $personaPieces;
		$this->pieceTintColors = $pieceTintColors;
		$this->isVerified = $isVerified;
		$this->premium = $premium;
		$this->persona = $persona;
		$this->personaCapeOnClassic = $personaCapeOnClassic;
		$this->isPrimaryUser = $isPrimaryUser;
		$this->override = $override;
		$this->trustedSkinFlag = $trustedSkinFlag;
		$this->profileHash = $profileHash;
	}

	public function getSkinId() : string{
		return $this->skinId;
	}

	public function getPlayFabId() : string{ return $this->playFabId; }

	public function getResourcePatch() : string{
		return $this->resourcePatch;
	}

	public function getSkinImage() : SkinImage{
		return $this->skinImage;
	}

	/**
	 * @return SkinAnimation[]
	 */
	public function getAnimations() : array{
		return $this->animations;
	}

	public function getCapeImage() : SkinImage{
		return $this->capeImage;
	}

	public function getGeometryData() : string{
		return $this->geometryData;
	}

	public function getGeometryDataEngineVersion() : string{ return $this->geometryDataEngineVersion; }

	public function getAnimationData() : string{
		return $this->animationData;
	}

	public function getCapeId() : string{
		return $this->capeId;
	}

	public function getFullSkinId() : string{
		return $this->fullSkinId;
	}

	public function getArmSize() : int{
		return $this->armSize;
	}

	public function getSkinColor() : int{
		return $this->skinColor;
	}

	/**
	 * @return PersonaSkinPiece[]
	 */
	public function getPersonaPieces() : array{
		return $this->personaPieces;
	}

	/**
	 * @return PersonaPieceTintColor[]
	 */
	public function getPieceTintColors() : array{
		return $this->pieceTintColors;
	}

	public function isPersona() : bool{
		return $this->persona;
	}

	public function isPremium() : bool{
		return $this->premium;
	}

	public function isPersonaCapeOnClassic() : bool{
		return $this->personaCapeOnClassic;
	}

	public function isPrimaryUser() : bool{
		return $this->isPrimaryUser;
	}

	public function isOverride() : bool{
		return $this->override;
	}

	public function isVerified() : bool{
		return $this->isVerified;
	}

	public function getTrustedSkinFlag() : string{
		return $this->trustedSkinFlag;
	}

	public function getProfileHash() : string{
		return $this->profileHash;
	}

	/**
	 * @internal
	 */
	public function setVerified(bool $verified) : void{
		$this->isVerified = $verified;
	}

	public static function convertArmSize(string $armSize) : int{
		return match ($armSize) {
			"slim" => SkinData::ARM_SIZE_SLIM,
			"wide", "" => SkinData::ARM_SIZE_WIDE,
			default => throw new \InvalidArgumentException("Unknown arm size \"$armSize\"")
		};
	}

	public static function convertColor(string $color) : int{
		$hex = ltrim($color, '#');
		if($hex === '' || $hex === '0'){
			return 0;
		}

		return (int) hexdec($hex);
	}
}
