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

namespace pocketmine\block;

use pocketmine\block\state\BlockState;
use pocketmine\block\state\BlockStateKeyEncoder;
use pocketmine\block\state\StateData;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;

abstract class StatefulBlock extends Block implements BlockState{

	private ?int $cachedRuntimeId = null;
	/** @var array<string, int|bool|string>|null */
	private ?array $cachedStates = null;

	final public function getRuntimeId() : int{
		$states = new StateData();
		$this->onSerialize($states);

		$all = $states->getAll();
		if($this->cachedRuntimeId !== null && $all === $this->cachedStates){
			return $this->cachedRuntimeId;
		}

		$rid = RuntimeBlockMapping::fromStateHash(BlockStateKeyEncoder::hash($this->id, $all));
		$this->cachedStates = $all;
		$this->cachedRuntimeId = $rid;

		return $rid;
	}
}
