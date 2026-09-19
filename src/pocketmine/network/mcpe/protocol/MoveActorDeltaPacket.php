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

class MoveActorDeltaPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::MOVE_ACTOR_DELTA_PACKET;

	public int $entityRuntimeId;
	public ?float $xPos = null;
	public ?float $yPos = null;
	public ?float $zPos = null;
	public ?float $xRot = null;
	public ?float $yRot = null;
	public ?float $zRot = null;
	public bool $onGround = false;
	public bool $teleport = false;
	public bool $forceMoveLocalEntity = false;
	public bool $forceCompletion = false;
	public int $ticks = 0;

	private function maybeReadCoord() : ?float{
		if($this->getBool()){
			return $this->getLFloat();
		}
		return null;
	}

	private function maybeReadRotation() : ?float{
		if($this->getBool()){
			return $this->getByteRotation();
		}
		return null;
	}

	protected function decodePayload() : void{
		$this->entityRuntimeId = $this->getEntityRuntimeId();
		$this->xPos = $this->maybeReadCoord();
		$this->yPos = $this->maybeReadCoord();
		$this->zPos = $this->maybeReadCoord();
		$this->xRot = $this->maybeReadRotation();
		$this->yRot = $this->maybeReadRotation();
		$this->zRot = $this->maybeReadRotation();
		$this->onGround = $this->getBool();
		$this->teleport = $this->getBool();
		$this->forceMoveLocalEntity = $this->getBool();
		$this->forceCompletion = $this->getBool();
		$this->ticks = $this->getUnsignedVarLong();
	}

	private function maybeWriteCoord(?float $val) : void{
		$this->putBool($val !== null);
		if($val !== null){
			$this->putLFloat($val);
		}
	}

	private function maybeWriteRotation(?float $val) : void{
		$this->putBool($val !== null);
		if($val !== null){
			$this->putByteRotation($val);
		}
	}

	protected function encodePayload() : void{
		$this->putEntityRuntimeId($this->entityRuntimeId);
		$this->maybeWriteCoord($this->xPos);
		$this->maybeWriteCoord($this->yPos);
		$this->maybeWriteCoord($this->zPos);
		$this->maybeWriteRotation($this->xRot);
		$this->maybeWriteRotation($this->yRot);
		$this->maybeWriteRotation($this->zRot);
		$this->putBool($this->onGround);
		$this->putBool($this->teleport);
		$this->putBool($this->forceMoveLocalEntity);
		$this->putBool($this->forceCompletion);
		$this->putUnsignedVarLong($this->ticks);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleMoveActorDelta($this);
	}
}
