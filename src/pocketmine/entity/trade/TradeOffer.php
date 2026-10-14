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
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use RuntimeException;

class TradeOffer{

	public const RECIPE_BASE = 50000;
	public const UNLIMITED_NBT_MAX_USES = 9999;

	/** @var int */
	private static $nextNetId = self::RECIPE_BASE;

	/** @var int */
	private $netId;
	/** @var TradeItem */
	private $buyA;
	/** @var TradeItem|null */
	private $buyB;
	/** @var TradeItem */
	private $sell;
	/** @var int */
	private $uses = 0;
	/** @var int */
	private $maxUses;
	/** @var bool */
	private $unlimited;
	/** @var int */
	private $traderExp;
	/** @var int */
	private $rewardExp;
	/** @var int */
	private $tier;

	public function __construct(TradeItem $buyA, TradeItem $sell, ?TradeItem $buyB = null, int $maxUses = 12, bool $unlimited = false, int $traderExp = 1, int $rewardExp = 1, int $tier = 0, ?int $netId = null){
		if(!$unlimited && $maxUses < 1){
			throw new InvalidArgumentException("Trade max uses must be positive, got $maxUses");
		}

		$this->buyA = $buyA;
		$this->sell = $sell;
		$this->buyB = $buyB;
		$this->maxUses = $maxUses;
		$this->unlimited = $unlimited;
		$this->traderExp = $traderExp;
		$this->rewardExp = $rewardExp;
		$this->tier = $tier;

		if($netId === null){
			$this->netId = self::$nextNetId++;
		}else{
			$this->netId = $netId;
			if($netId >= self::$nextNetId){
				self::$nextNetId = $netId + 1;
			}
		}
	}

	public function getNetId() : int{
		return $this->netId;
	}

	public function getBuyA() : TradeItem{
		return $this->buyA;
	}

	public function getBuyB() : ?TradeItem{
		return $this->buyB;
	}

	public function getSell() : TradeItem{
		return $this->sell;
	}

	public function getUses() : int{
		return $this->uses;
	}

	public function addUses(int $crafts) : void{
		$this->uses += $crafts;
	}

	public function getMaxUses() : int{
		return $this->maxUses;
	}

	public function isUnlimited() : bool{
		return $this->unlimited;
	}

	public function getTraderExp() : int{
		return $this->traderExp;
	}

	public function getRewardExp() : int{
		return $this->rewardExp;
	}

	public function getTier() : int{
		return $this->tier;
	}

	public function canCraft(int $crafts = 1) : bool{
		if($crafts < 1){
			return false;
		}
		return $this->unlimited || $this->uses + $crafts <= $this->maxUses;
	}

	public function matchesInputs(Item $inputA, ?Item $inputB, int $crafts = 1) : bool{
		if(!$this->canCraft($crafts)){
			return false;
		}
		if(!$this->buyA->matches($inputA, $crafts)){
			return false;
		}

		if($this->buyB !== null){
			if($inputB === null || !$this->buyB->matches($inputB, $crafts)){
				return false;
			}
		}
		return true;
	}

	private static function namedItemTag(TradeItem $item, string $name) : CompoundTag{
		$tag = $item->toNbt();
		$tag->setName($name);
		return $tag;
	}

	public function toNbt() : CompoundTag{
		$tag = new CompoundTag("");
		$tag->setTag(self::namedItemTag($this->buyA, "buyA"));
		if($this->buyB !== null){
			$tag->setTag(self::namedItemTag($this->buyB, "buyB"));
		}

		$tag->setTag(self::namedItemTag($this->sell, "sell"));
		$tag->setInt("buyCountA", $this->buyA->getCount());
		$tag->setInt("buyCountB", $this->buyB !== null ? $this->buyB->getCount() : 0);
		$tag->setInt("demand", 0);
		$tag->setInt("netId", $this->netId);
		$tag->setInt("uses", $this->unlimited ? 0 : $this->uses);
		$tag->setInt("maxUses", $this->unlimited ? self::UNLIMITED_NBT_MAX_USES : $this->maxUses);
		$tag->setFloat("priceMultiplierA", 0.0);
		$tag->setFloat("priceMultiplierB", 0.0);
		$tag->setTag(new ByteTag("rewardExp", $this->rewardExp));
		$tag->setInt("traderExp", $this->traderExp);
		$tag->setInt("tier", $this->tier);
		return $tag;
	}

	private static function optionalInt(CompoundTag $tag, string $name) : ?int{
		$inner = $tag->getTag($name);
		return $inner instanceof IntTag ? $inner->getValue() : null;
	}

	public static function fromNbt(CompoundTag $tag) : ?self{
		try{
			$buyATag = $tag->getCompoundTag("buyA");
			$sellTag = $tag->getCompoundTag("sell");
			if($buyATag === null || $sellTag === null){
				return null;
			}

			$buyAItem = TradeItem::fromNbt($buyATag);
			$sellItem = TradeItem::fromNbt($sellTag);
			if($buyAItem === null || $sellItem === null){
				return null;
			}

			$buyBItem = null;
			$buyBTag = $tag->getCompoundTag("buyB");
			if($buyBTag !== null){
				$buyBItem = TradeItem::fromNbt($buyBTag);
				if($buyBItem === null){
					return null;
				}
			}

			$maxUses = self::optionalInt($tag, "maxUses") ?? 12;
			$uses = self::optionalInt($tag, "uses") ?? 0;
			$unlimited = $maxUses >= self::UNLIMITED_NBT_MAX_USES;
			$offer = new self(
				$buyAItem,
				$sellItem,
				$buyBItem,
				$unlimited ? 12 : max(1, $maxUses),
				$unlimited,
				self::optionalInt($tag, "traderExp") ?? 1,
				$tag->getByte("rewardExp", 1),
				self::optionalInt($tag, "tier") ?? 0,
				self::optionalInt($tag, "netId")
			);

			$offer->uses = max(0, $uses);
			return $offer;
		}catch(InvalidArgumentException | RuntimeException $e){
			return null;
		}
	}
}
