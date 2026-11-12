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
use pocketmine\network\mcpe\protocol\types\chat\ChatFormatting;
use pocketmine\network\mcpe\protocol\types\chat\SignedChat;

final class ChatPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::CHAT_PACKET;

	private SignedChat $signedChat;
	private ?string $roomId = null;
	private ?ChatFormatting $formatting = null;

	/**
	 * @generate-create-func
	 */
	public static function create(SignedChat $signedChat, ?string $roomId = null, ?ChatFormatting $formatting = null) : self{
		$result = new self;
		$result->signedChat = $signedChat;
		$result->roomId = $roomId;
		$result->formatting = $formatting;
		return $result;
	}

	public function getSignedChat() : SignedChat{
		return $this->signedChat;
	}

	public function getRoomId() : ?string{
		return $this->roomId;
	}

	public function getFormatting() : ?ChatFormatting{
		return $this->formatting;
	}

	protected function decodePayload() : void{
		$this->signedChat = SignedChat::read($this);
		$this->roomId = $this->readOptional(fn() => $this->getString());
		$this->formatting = $this->readOptional(fn() => ChatFormatting::read($this));
	}

	protected function encodePayload() : void{
		$this->signedChat->write($this);
		$this->writeOptional($this->roomId, fn(string $roomId) => $this->putString($roomId));
		$this->writeOptional($this->formatting, fn(ChatFormatting $formatting) => $formatting->write($this));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleChat($this);
	}
}
