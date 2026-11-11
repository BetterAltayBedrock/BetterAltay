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
use pocketmine\network\mcpe\protocol\types\MusicPlaybackState;

final class ClientboundOnlineMusicPlaybackResponsePacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_ONLINE_MUSIC_PLAYBACK_RESPONSE_PACKET;

	private int $requestId;
	private MusicPlaybackState $state;
	private string $sharedMetadata;
	private string $playbackContent;

	/**
	 * @generate-create-func
	 */
	public static function create(int $requestId, MusicPlaybackState $state, string $sharedMetadata, string $playbackContent) : self{
		$result = new self;
		$result->requestId = $requestId;
		$result->state = $state;
		$result->sharedMetadata = $sharedMetadata;
		$result->playbackContent = $playbackContent;
		return $result;
	}

	public function getRequestId() : int{
		return $this->requestId;
	}

	public function getState() : MusicPlaybackState{
		return $this->state;
	}

	public function getSharedMetadata() : string{
		return $this->sharedMetadata;
	}

	public function getPlaybackContent() : string{
		return $this->playbackContent;
	}

	protected function decodePayload() : void{
		$this->requestId = $this->getLInt();
		$this->state = MusicPlaybackState::from($this->getString());
		$this->sharedMetadata = $this->getString();
		$this->playbackContent = $this->getString();
	}

	protected function encodePayload() : void{
		$this->putLInt($this->requestId);
		$this->putString($this->state->value);
		$this->putString($this->sharedMetadata);
		$this->putString($this->playbackContent);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleClientboundOnlineMusicPlaybackResponse($this);
	}
}
