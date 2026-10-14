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

namespace pocketmine\entity\trade;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\ItemTranslator;
use RuntimeException;

class TradeItem{

	/** @var int */
	private $id;
	/** @var int */
	private $meta;
	/** @var int */
	private $count;

	public function __construct(int $id, int $meta, int $count){
		if($id === Item::AIR || $id <= 0 || !ItemFactory::isRegistered($id)){
			throw new InvalidArgumentException("Unknown trade item id $id");
		}
		if($count <= 0){
			throw new InvalidArgumentException("Trade item count must be positive, got $count");
		}

		$this->id = $id;
		$this->meta = $meta;
		$this->count = $count;
	}

	public function getId() : int{
		return $this->id;
	}

	public function getMeta() : int{
		return $this->meta;
	}

	public function getCount() : int{
		return $this->count;
	}

	public function matches(Item $item, int $crafts = 1) : bool{
		if($crafts < 1 || $item->isNull()){
			return false;
		}
		if($item->getId() !== $this->id){
			return false;
		}
		if($item->getDamage() !== $this->meta){
			return false;
		}

		return $item->getCount() >= $this->count * $crafts;
	}

	public function toItem(int $crafts = 1) : Item{
		return Item::get($this->id, $this->meta, $this->count * $crafts);
	}

	public function toNbt() : CompoundTag{
		return new CompoundTag("", [
			new StringTag("Name", ItemTranslator::getInstance()->toStringId($this->id, $this->meta)),
			new ByteTag("Count", $this->count),
			new ShortTag("Damage", $this->meta)
		]);
	}

	public static function fromNbt(CompoundTag $tag) : ?self{
		try{
			$name = $tag->getString("Name", "");
			if($name === ""){
				return null;
			}

			[$id, $fallbackMeta] = ItemTranslator::getInstance()->fromStringId($name);
			$count = $tag->getByte("Count", 0);
			$meta = $tag->getShort("Damage", $fallbackMeta);

			if($count <= 0){
				return null;
			}
			return new self($id, $meta, $count);
		}catch(InvalidArgumentException | RuntimeException $e){
			return null;
		}
	}
}
