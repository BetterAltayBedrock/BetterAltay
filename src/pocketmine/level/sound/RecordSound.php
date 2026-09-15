<?php

declare(strict_types=1);

namespace pocketmine\level\sound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\RecordStartedPacket;

class RecordSound extends Sound{

	private static int $soundHandleCount = 1;

	private int $serverSoundHandle;

	public function __construct(Vector3 $pos, private string $soundId, ?int $existingHandle = null){
		parent::__construct($pos->x, $pos->y, $pos->z);
		$this->serverSoundHandle = $existingHandle ?? self::$soundHandleCount++;
	}

	public function getServerSoundHandle() : int{
		return $this->serverSoundHandle;
	}

	public function encode(){
		return [
			PlaySoundPacket::create($this->soundId, $this->x + 0.5, $this->y + 0.5, $this->z + 0.5, 1, 1, 0, true, $this->serverSoundHandle),
			RecordStartedPacket::create($this->x, $this->y, $this->z, $this->serverSoundHandle)
		];
	}

}