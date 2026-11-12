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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\sound\SoundAttachOptions;

class PlaySoundPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::PLAY_SOUND_PACKET;

	public string $soundName;
	public float $x;
	public float $y;
	public float $z;
	public float $volume;
	public float $pitch;
	public int $loopCount = 0;
	public bool $bypassListenerRangeCheck = false;
	public ?int $serverSoundHandle = null;
	public ?float $playbackPositionSeconds = null;
	public ?float $startDelaySeconds = null;
	public ?SoundAttachOptions $soundAttachOptions = null;

	public static function create(
		string $soundName,
		float $x,
		float $y,
		float $z,
		float $volume = 1.0,
		float $pitch = 1.0,
		int $loopCount = 0,
		bool $bypassListenerRangeCheck = false,
		?int $serverSoundHandle = null,
		?float $playbackPositionSeconds = null,
		?float $startDelaySeconds = null,
		?SoundAttachOptions $soundAttachOptions = null
	) : self{
		$result = new self;
		$result->soundName = $soundName;
		$result->x = $x;
		$result->y = $y;
		$result->z = $z;
		$result->volume = $volume;
		$result->pitch = $pitch;
		$result->loopCount = $loopCount;
		$result->bypassListenerRangeCheck = $bypassListenerRangeCheck;
		$result->serverSoundHandle = $serverSoundHandle;
		$result->playbackPositionSeconds = $playbackPositionSeconds;
		$result->startDelaySeconds = $startDelaySeconds;
		$result->soundAttachOptions = $soundAttachOptions;

		return $result;
	}

	protected function decodePayload() : void{
		$this->soundName = $this->getString();
		$this->getBlockPosition($this->x, $this->y, $this->z);
		$this->x /= 8;
		$this->y /= 8;
		$this->z /= 8;
		$this->volume = $this->getLFloat();
		$this->pitch = $this->getLFloat();
		$this->loopCount = $this->getVarInt();
		$this->bypassListenerRangeCheck = $this->getBool();
		$this->serverSoundHandle = $this->readOptional(fn() => $this->getLLong());
		$this->playbackPositionSeconds = $this->readOptional(fn() => $this->getLFloat());
		$this->startDelaySeconds = $this->readOptional(fn() => $this->getLFloat());
		$this->soundAttachOptions = $this->readOptional(fn() => SoundAttachOptions::read($this));
	}

	protected function encodePayload() : void{
		$this->putString($this->soundName);
		$this->putBlockPosition((int) ($this->x * 8), (int) ($this->y * 8), (int) ($this->z * 8));
		$this->putLFloat($this->volume);
		$this->putLFloat($this->pitch);
		$this->putVarInt($this->loopCount);
		$this->putBool($this->bypassListenerRangeCheck);
		$this->writeOptional($this->serverSoundHandle, fn(int $serverSoundHandle) => $this->putLLong($serverSoundHandle));
		$this->writeOptional($this->playbackPositionSeconds, fn(float $playbackPositionSeconds) => $this->putLFloat($playbackPositionSeconds));
		$this->writeOptional($this->startDelaySeconds, fn(float $startDelaySeconds) => $this->putLFloat($startDelaySeconds));
		$this->writeOptional($this->soundAttachOptions, fn(SoundAttachOptions $options) => $options->write($this));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handlePlaySound($this);
	}
}
