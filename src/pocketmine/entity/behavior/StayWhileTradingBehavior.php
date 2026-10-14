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

namespace pocketmine\entity\behavior;

use pocketmine\entity\passive\Villager;

class StayWhileTradingBehavior extends Behavior{

	/** @var Villager */
	private $villager;

	public function __construct(Villager $mob){
		parent::__construct($mob);
		$this->villager = $mob;
		$this->mutexBits = 1;
	}

	public function canStart() : bool{
		return $this->villager->getTradingPlayer() !== null;
	}

	public function onStart() : void{
		$this->villager->getNavigator()->clearPath(true);
		$this->villager->setMoveForward(0.0);
		$this->villager->setMoveStrafing(0.0);
	}

	public function onTick() : void{
		$this->villager->getNavigator()->clearPath(true);
		$this->villager->setMoveForward(0.0);
		$this->villager->setMoveStrafing(0.0);
	}
}
