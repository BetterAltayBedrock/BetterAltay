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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\entity\ClimateEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\Mob;
use pocketmine\entity\BiommeVariantEntity;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use function lcg_value;

class SpawnEgg extends Item{

	public function onActivate(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector) : bool{
		$nbt = Entity::createBaseNBT($blockReplace->add(0.5, 0, 0.5), null, lcg_value() * 360, 0);

		if($this->hasCustomName()){
			$nbt->setString("CustomName", $this->getCustomName());
		}

		$entity = Entity::createEntity(str_replace("_spawn_egg", "", $this->id), $player->getLevelNonNull(), $nbt);

		if($entity instanceof Entity){
			$biome = $player->getLevelNonNull()->getBiome($entity->getFloorX(), $entity->getFloorZ());
			if($entity instanceof ClimateEntity){
				$entity->setClimateVariantFromBiome($biome);
			}elseif($entity instanceof BiommeVariantEntity){
				$entity->setVariantFromBiome($biome);
			}

			if($entity instanceof Mob){
				$entity->setImmobile(!Server::getInstance()->mobAiEnabled);
			}
			$this->pop();
			$entity->spawnToAll();
			return true;
		}

		return false;
	}
}