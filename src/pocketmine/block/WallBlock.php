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

use pocketmine\block\state\StateData;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;

class WallBlock extends StatefulBlock{

	protected string $north = "none";
	protected string $east = "none";
	protected string $south = "none";
	protected string $west = "none";
	protected bool $postBit = true;

	public function __construct(string $id, string $name){
		parent::__construct($id, 0, $name, $id);
	}

	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		$this->calculateConnections();
		$this->getLevelNonNull()->setBlock($blockReplace, $this);
		return true;
	}

	public function onNearbyBlockChange() : void{
		if($this->getLevel() === null) return;

		$before = $this->getRuntimeId();
		$this->calculateConnections();
		if($this->getRuntimeId() !== $before){
			$this->getLevelNonNull()->setBlock($this, $this, true, false);
		}
	}

	public function calculateConnections() : void{
		$faces = [
			Vector3::SIDE_NORTH => "north",
			Vector3::SIDE_EAST => "east",
			Vector3::SIDE_SOUTH => "south",
			Vector3::SIDE_WEST => "west"
		];
		$hasSolidAboveMe = $this->getSide(Vector3::SIDE_UP)->isSolid();

		foreach($faces as $side => $prop){
			$adjBlock = $this->getSide($side);
			if($adjBlock instanceof Air){
				$this->{$prop} = "none";
				continue;
			}

			$canConnect = $adjBlock->isSolid()
				|| $adjBlock instanceof WallBlock
				|| $adjBlock instanceof Fence
				|| $adjBlock instanceof FenceGate;

			if($canConnect){
				$this->{$prop} = $hasSolidAboveMe ? "tall" : "short";
			}else{
				$this->{$prop} = "none";
			}
		}

		$this->calculatePostBit();
	}

	public function calculatePostBit() : void{
		$n = $this->north !== "none";
		$s = $this->south !== "none";
		$e = $this->east !== "none";
		$w = $this->west !== "none";

		$blockAbove = $this->getSide(Vector3::SIDE_UP);
		$connectsAbove = $blockAbove->isSolid()
			|| $blockAbove instanceof Torch
			|| $blockAbove instanceof IronBars
			|| $blockAbove instanceof GlassPane
			|| $blockAbove instanceof Fence;

		$isStraight = ($e && $w && !$n && !$s) || ($n && $s && !$e && !$w);
		$this->postBit = $connectsAbove || !$isStraight;
	}

	public function onSerialize(StateData $state) : void{
		$state->setAll([
			StateData::WALL_CONNECTION_TYPE_NORTH => $this->north,
			StateData::WALL_CONNECTION_TYPE_EAST => $this->east,
			StateData::WALL_CONNECTION_TYPE_SOUTH => $this->south,
			StateData::WALL_CONNECTION_TYPE_WEST => $this->west,
			StateData::WALL_POST_BIT => $this->postBit
		]);
	}

	public function onDeserialize(StateData $state) : void{
		$this->north = $state->getString(StateData::WALL_CONNECTION_TYPE_NORTH, "none");
		$this->east = $state->getString(StateData::WALL_CONNECTION_TYPE_EAST, "none");
		$this->south = $state->getString(StateData::WALL_CONNECTION_TYPE_SOUTH, "none");
		$this->west = $state->getString(StateData::WALL_CONNECTION_TYPE_WEST, "none");
		$this->postBit = $state->getBool(StateData::WALL_POST_BIT, true);
	}
}