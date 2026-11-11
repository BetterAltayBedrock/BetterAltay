<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBinaryStream;

final class PassengerOfBlockArguments{

	public function __construct(
		private Vector3 $blockPosition,
		private Vector3 $offset,
		private float $rotation,
		private float $rotationLimit,
		/** @see PassengerOfBlockEmoteType */
		private int $emoteType,
		private int $blockType
	){
	}

	public function getBlockPosition() : Vector3{ return $this->blockPosition; }

	public function getOffset() : Vector3{ return $this->offset; }

	public function getRotation() : float{ return $this->rotation; }

	public function getRotationLimit() : float{ return $this->rotationLimit; }

	public function getEmoteType() : int{ return $this->emoteType; }

	public function getBlockType() : int{ return $this->blockType; }

	public static function read(NetworkBinaryStream $in) : self{
		$x = $y = $z = 0;
		$in->getBlockPosition($x, $y, $z);
		$blockPosition = new Vector3($x, $y, $z);
		$offset = $in->getVector3();
		$rotation = $in->getLFloat();
		$rotationLimit = $in->getLFloat();
		$emoteType = $in->getByte();
		$blockType = $in->getLLong();

		return new self($blockPosition, $offset, $rotation, $rotationLimit, $emoteType, $blockType);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putBlockPosition((int) $this->blockPosition->x, (int) $this->blockPosition->y, (int) $this->blockPosition->z);
		$out->putVector3($this->offset);
		$out->putLFloat($this->rotation);
		$out->putLFloat($this->rotationLimit);
		$out->putByte($this->emoteType);
		$out->putLLong($this->blockType);
	}
}