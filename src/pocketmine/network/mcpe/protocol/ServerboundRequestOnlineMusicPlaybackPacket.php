<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;

final class ServerboundRequestOnlineMusicPlaybackPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_REQUEST_ONLINE_MUSIC_PLAYBACK_PACKET;

	private int $requestId;
	private string $soundEventName;

	/**
	 * @generate-create-func
	 */
	public static function create(int $requestId, string $soundEventName) : self{
		$result = new self;
		$result->requestId = $requestId;
		$result->soundEventName = $soundEventName;
		return $result;
	}

	public function getRequestId() : int{
		return $this->requestId;
	}

	public function getSoundEventName() : string{
		return $this->soundEventName;
	}

	protected function decodePayload() : void{
		$this->requestId = $this->getLInt();
		$this->soundEventName = $this->getString();
	}

	protected function encodePayload() : void{
		$this->putLInt($this->requestId);
		$this->putString($this->soundEventName);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleServerboundRequestOnlineMusicPlayback($this);
	}
}
