<?php

declare(strict_types=1);

namespace pocketmine\level\sound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ClientboundUpdateSoundDataPacket;
use pocketmine\network\mcpe\protocol\types\SoundData;

class RecordStopSound extends Sound{

	public function __construct(Vector3 $pos, private int $serverSoundHandle){
		parent::__construct($pos->x, $pos->y, $pos->z);
	}

	public function encode(){
		return ClientboundUpdateSoundDataPacket::create($this->serverSoundHandle, SoundData::stop());
	}

}