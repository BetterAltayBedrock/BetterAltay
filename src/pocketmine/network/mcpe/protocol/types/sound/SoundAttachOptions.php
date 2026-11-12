<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\sound;

use pocketmine\network\mcpe\NetworkBinaryStream;

final class SoundAttachOptions{

	public function __construct(
		private int $entityRuntimeId
	){}

	public function getEntityRuntimeIdField() : int{ return $this->entityRuntimeId; } //TODO: rename this

	public static function read(NetworkBinaryStream $in) : self{
		return new self($in->getEntityRuntimeId());
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putEntityRuntimeId($this->entityRuntimeId);
	}
}
