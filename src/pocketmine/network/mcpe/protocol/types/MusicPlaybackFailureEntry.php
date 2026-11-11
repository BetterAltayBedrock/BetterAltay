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

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;

final class MusicPlaybackFailureEntry{

	public function __construct(
		private MusicPlaybackState $state,
		private string $description
	){}

	public function getState() : MusicPlaybackState{
		return $this->state;
	}

	public function getDescription() : string{
		return $this->description;
	}

	public static function read(NetworkBinaryStream $in) : self{
		$state = MusicPlaybackState::from($in->getString());
		$description = $in->getString();
		return new self($state, $description);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putString($this->state->value);
		$out->putString($this->description);
	}
}
