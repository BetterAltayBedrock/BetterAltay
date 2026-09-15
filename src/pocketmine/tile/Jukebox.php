<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\tile;

use pocketmine\item\Item;
use pocketmine\item\Record;
use pocketmine\level\sound\RecordSound;
use pocketmine\level\sound\RecordStopSound;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player;

class Jukebox extends Spawnable{

	public const TAG_RECORD_ITEM = "RecordItem";

	/** @var Record|null */
	protected ?Record $recordItem = null;
	private ?int $soundHandle = null;

	public function setRecordItem(?Record $item) : void{
		$this->recordItem = $item;
		$this->onChanged();
	}

	public function getRecordItem() : ?Record{
		return $this->recordItem;
	}

	public function getSoundHandle() : ?int{
		return $this->soundHandle;
	}

	public function setSoundHandle(?int $soundHandle) : void{
		$this->soundHandle = $soundHandle;
	}

	public function playDisc(?Player $player = null) : void{
		if($this->getRecordItem() instanceof Record){
			$sound = new RecordSound($this, $this->getRecordItem()->getSoundId());
			$this->setSoundHandle($sound->getServerSoundHandle());
			$this->getLevel()->addSound($sound);
			if($player instanceof Player){
				$pk = new TextPacket();
				$pk->type = TextPacket::TYPE_JUKEBOX_POPUP;
				$pk->needsTranslation = true;
				$pk->message = "record.nowPlaying";
				$pk->parameters = [
					ucwords(str_ireplace([
						"record", ".", "_"
					], [
						"", "", " "
					], $this->getRecordItem()->getSoundId()))
				];
				$player->sendDataPacket($pk);
			}

			$this->scheduleUpdate();
		}
	}

	public function stopDisc() : void{
		if($this->getRecordItem() instanceof Record && $this->soundHandle !== null){
			$sound = new RecordStopSound($this, $this->getSoundHandle());
			$this->getLevel()->addSound($sound);
			$this->setSoundHandle(null);
		}
	}

	public function dropDisc() : void{
		if($this->getRecordItem() instanceof Record){
			$this->stopDisc();
			$this->level->dropItem($this->add(0.5, 1, 0.5), $this->getRecordItem());
			$this->setRecordItem(null);
		}
	}

	public function hasRecordItem() : bool{
		return $this->recordItem instanceof Record;
	}

	public function getDefaultName() : string{
		return "Jukebox";
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		if($nbt->hasTag(self::TAG_RECORD_ITEM)){
			$item = Item::nbtDeserialize($nbt->getCompoundTag(self::TAG_RECORD_ITEM));
			$this->recordItem = $item instanceof Record ? $item : null;

			$this->scheduleUpdate();
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->recordItem !== null){
			$nbt->setTag($this->recordItem->nbtSerialize(-1, self::TAG_RECORD_ITEM));
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->writeSaveData($nbt);
	}

	public function spawnTo(Player $player) : bool{
		if($this->hasRecordItem() && $this->soundHandle !== null){
			$sound = new RecordSound($this, $this->getRecordItem()->getSoundId(), $this->soundHandle);
			$this->getLevel()->addSound($sound, [$player]);
		}
		return parent::spawnTo($player);
	}
}