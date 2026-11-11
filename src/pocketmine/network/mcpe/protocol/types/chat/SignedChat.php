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

namespace pocketmine\network\mcpe\protocol\types\chat;

use pocketmine\network\mcpe\NetworkBinaryStream;

final class SignedChat{

	public function __construct(
		private string $keyId,
		private string $senderId,
		private string $roomId,
		private string $messageId,
		private string $scanText,
		private string $redactedText,
		private string $signature
	){}

	public function getKeyId() : string{ return $this->keyId; }

	public function getSenderId() : string{ return $this->senderId; }

	public function getRoomId() : string{ return $this->roomId; }

	public function getMessageId() : string{ return $this->messageId; }

	public function getScanText() : string{ return $this->scanText; }

	public function getRedactedText() : string{ return $this->redactedText; }

	public function getSignature() : string{ return $this->signature; }

	public function write(NetworkBinaryStream $out) : void{
		$out->putString($this->keyId);
		$out->putString($this->senderId);
		$out->putString($this->roomId);
		$out->putString($this->messageId);
		$out->putString($this->scanText);
		$out->putString($this->redactedText);
		$out->putString($this->signature);
	}

	public static function read(NetworkBinaryStream $in) : self{
		return new self(
			$in->getString(),
			$in->getString(),
			$in->getString(),
			$in->getString(),
			$in->getString(),
			$in->getString(),
			$in->getString()
		);
	}
}
