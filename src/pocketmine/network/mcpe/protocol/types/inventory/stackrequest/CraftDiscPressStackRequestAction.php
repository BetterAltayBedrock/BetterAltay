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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pocketmine\network\mcpe\NetworkBinaryStream;

/**
 * Spec name: ItemStackRequestCraftDiscPressAction
 */
final class CraftDiscPressStackRequestAction extends ItemStackRequestAction{

	public static function getTypeId() : int{
		return ItemStackRequestActionType::CRAFTING_DISC_PRESS;
	}

	public function __construct(
		private string $trackSoundEventId,
		private int $numCrafts
	){}

	public function getTrackSoundEventId() : string{ return $this->trackSoundEventId; }

	public function getNumCrafts() : int{ return $this->numCrafts; }

	public static function read(NetworkBinaryStream $in) : self{
		$trackSoundEventId = $in->getString();
		$numCrafts = $in->getByte();
		return new self($trackSoundEventId, $numCrafts);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putString($this->trackSoundEventId);
		$out->putByte($this->numCrafts);
	}
}
