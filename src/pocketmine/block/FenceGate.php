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

use pocketmine\block\material\WoodType;
use pocketmine\block\state\StateData;
use pocketmine\item\Item;
use pocketmine\level\sound\DoorSound;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;

class FenceGate extends StatefulBlock{

	protected string $direction = "south";
	protected bool $open = false;
	protected bool $inWall = false;

	public function __construct(private WoodType $material, int $meta = 0){
		$this->id = "minecraft:" . $this->material->getType() . "_fence_gate";
		if ($this->material->equals(WoodType::OAK())) {
			$this->id = self::FENCE_GATE;
		}

		$this->meta = $meta;
	}

	public function getHardness() : float{
		return 2;
	}

	public function getToolType() : int{
		return BlockToolType::TYPE_AXE;
	}

	public function isTransparent() : bool{
		return true;
	}

	public function getLightFilter() : int{
		return 0;
	}

	public function isPassable() : bool{
		return $this->open;
	}

	protected function recalculateBoundingBox() : ?AxisAlignedBB{
		if($this->open){
			return null;
		}

		return match ($this->direction) {
			"north", "south" => new AxisAlignedBB(
				$this->x,
				$this->y,
				$this->z + 0.375,
				$this->x + 1,
				$this->y + 1.5,
				$this->z + 0.625
			),
			default => new AxisAlignedBB(
				$this->x + 0.375,
				$this->y,
				$this->z,
				$this->x + 0.625,
				$this->y + 1.5,
				$this->z + 1
			)
		};
	}

	public function getName() : string{
		return $this->material->getName() . " Fence Gate";
	}

	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		if($player instanceof Player){
			$this->direction = match (($player->getDirection() - 1) & 0x03) {
				0 => "south",
				1 => "west",
				2 => "north",
				3 => "east"
			};
		}

		$this->calculateInWallState();
		$this->getLevelNonNull()->setBlock($blockReplace, $this, true);
		return true;
	}

	public function onNearbyBlockChange() : void{
		if($this->getLevel() === null) return;

		$oldInWall = $this->inWall;
		$this->calculateInWallState();

		if($oldInWall !== $this->inWall){
			$this->getLevelNonNull()->setBlock($this, $this, true, false);
		}
	}

	public function calculateInWallState() : void{
		$sides = match ($this->direction) {
			"north", "south" => [Vector3::SIDE_WEST, Vector3::SIDE_EAST],
			"east", "west" => [Vector3::SIDE_NORTH, Vector3::SIDE_SOUTH]
		};

		$this->inWall = $this->getSide($sides[0]) instanceof WallBlock
			|| $this->getSide($sides[1]) instanceof WallBlock;
	}

	public function onActivate(Item $item, Player $player = null) : bool{
		$this->open = !$this->open;

		if($player !== null && $this->open){
			$playerDir = match (($player->getDirection() - 1) & 0x03) {
				0 => "south",
				1 => "west",
				2 => "north",
				3 => "east"
			};

			$opposites = [
				"north" => "south",
				"south" => "north",
				"east" => "west",
				"west" => "east"
			];

			if($this->direction === $opposites[$playerDir]){
				$this->direction = $playerDir;
			}
		}

		$this->getLevelNonNull()->setBlock($this, $this, true);
		$this->level->addSound(new DoorSound($this));
		return true;
	}

	public function getFuelTime() : int{
		return 300;
	}

	public function getFlameEncouragement() : int{
		return 5;
	}

	public function getFlammability() : int{
		return 20;
	}

	public function getMaterial() : WoodType{
		return $this->material;
	}

	public function onSerialize(StateData $state) : void{
		$state->setAll([
			StateData::MINECRAFT_CARDINAL_DIRECTION => $this->direction,
			StateData::OPEN_BIT => $this->open,
			StateData::IN_WALL_BIT => $this->inWall
		]);
	}

	public function onDeserialize(StateData $state) : void{
		$this->direction = $state->getString(StateData::MINECRAFT_CARDINAL_DIRECTION, "south");
		$this->open = $state->getBool(StateData::OPEN_BIT);
		$this->inWall = $state->getBool(StateData::IN_WALL_BIT);
	}
}
