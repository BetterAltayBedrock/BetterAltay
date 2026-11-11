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

final class ChatFormatting{

	public function __construct(
		private ?string $namePrefix,
		private ?string $namePostfix,
		private ?string $messagePrefix,
		private ?string $messagePostfix
	){}

	public function getNamePrefix() : ?string{ return $this->namePrefix; }

	public function getNamePostfix() : ?string{ return $this->namePostfix; }

	public function getMessagePrefix() : ?string{ return $this->messagePrefix; }

	public function getMessagePostfix() : ?string{ return $this->messagePostfix; }

	public function write(NetworkBinaryStream $out) : void{
		$out->writeOptional($this->namePrefix, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->namePostfix, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->messagePrefix, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->messagePostfix, fn(string $value) => $out->putString($value));
	}

	public static function read(NetworkBinaryStream $in) : self{
		return new self(
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getString())
		);
	}
}
