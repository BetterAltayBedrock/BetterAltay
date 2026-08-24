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

namespace pocketmine\entity\projectile;

use pocketmine\entity\ClimateEntity;
use pocketmine\entity\ClimateTrait;
use pocketmine\entity\Entity;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\particle\ItemBreakParticle;
use function mt_rand;

class Egg extends Throwable implements ClimateEntity{
	use ClimateTrait;

	public const NETWORK_ID = self::EGG;

	protected function initEntity() : void{
		parent::initEntity();
		$this->initClimateNBT();
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		for($i = 0; $i < 6; ++$i){
			$this->level->addParticle(new ItemBreakParticle($this, ItemFactory::get(ItemIds::EGG)));
		}

		if(mt_rand(1, 8) === 1){
			$nbt = Entity::createBaseNBT($this);
			$nbt->setInt("ClimateVariant", $this->getClimateVariant());

			$chicken = Entity::createEntity("Chicken", $this->level, $nbt);

			if($chicken instanceof Entity){
				$chicken->setBaby();
				$chicken->spawnToAll();
			}
		}
	}
}
