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

namespace pocketmine\network\mcpe;

use pocketmine\entity\trade\TradeOffer;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\PlayerUIInventory;
use pocketmine\inventory\TradeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftingConsumeInputStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DestroyStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DropStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\SwapStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\Player;

final class ItemStackRequestHandler{

	private const CONTAINER_ARMOR = 6;
	private const CONTAINER_CONTAINER = 7;
	private const CONTAINER_HOTBAR_AND_INVENTORY = 12;
	private const CONTAINER_DYNAMIC = 66;
	private const CONTAINER_HOTBAR = 31;
	private const CONTAINER_INVENTORY = 32;
	private const CONTAINER_TRADE_INGREDIENT_1 = 34;
	private const CONTAINER_TRADE_INGREDIENT_2 = 35;
	private const CONTAINER_TRADE_RESULT = 36;
	private const CONTAINER_OFFHAND = 37;
	private const CONTAINER_TRADE2_INGREDIENT_1 = 50;
	private const CONTAINER_TRADE2_INGREDIENT_2 = 51;
	private const CONTAINER_TRADE2_RESULT = 52;
	private const CONTAINER_CURSOR = 62;
	private const CONTAINER_CREATIVE_OUTPUT = 63;

	private function __construct(){
		//NOOP
	}

	public static function handle(Player $player, ItemStackRequestPacket $packet) : bool{
		return self::handleRequests($player, $packet->getRequests());
	}

	/** @param ItemStackRequest[] $requests */
	public static function handleRequests(Player $player, array $requests) : bool{
		$handled = false;
		foreach($requests as $request){
			$result = self::processRequest($player, $request);
			if($result === null){
				continue;
			}

			$handled = true;
			$player->sendDataPacket(ItemStackResponsePacket::create([
				new ItemStackResponse($result ? ItemStackResponse::RESULT_OK : ItemStackResponse::RESULT_ERROR, $request->getRequestId(), [])
			]));

			if(!$result){
				self::resync($player);
			}
		}

		return $handled;
	}

	private static function processRequest(Player $player, ItemStackRequest $request) : ?bool{
		$tradeWindow = $player->findWindow(TradeInventory::class);
		if(!$tradeWindow instanceof TradeInventory && !self::involvesTradeCraft($request)){
			return null;
		}

		$hasTradeCraft = false;
		foreach($request->getActions() as $action){
			if($action instanceof CraftRecipeStackRequestAction && $action->getRecipeId() >= TradeOffer::RECIPE_BASE){
				$hasTradeCraft = true;
			}elseif(
				!$action instanceof TakeStackRequestAction &&
				!$action instanceof PlaceStackRequestAction &&
				!$action instanceof SwapStackRequestAction &&
				!$action instanceof DropStackRequestAction &&
				!$action instanceof DestroyStackRequestAction &&
				!$action instanceof CraftingConsumeInputStackRequestAction &&
				!$action instanceof MineBlockStackRequestAction
			){
				if($hasTradeCraft){
					return false;
				}
				return null;
			}
		}

		$ok = true;
		$tookTradePreview = false;
		foreach($request->getActions() as $action){
			if($action instanceof TakeStackRequestAction || $action instanceof PlaceStackRequestAction){
				$src = self::resolve($player, $action->getSource());
				$dst = self::resolve($player, $action->getDestination());

				if($src !== null && $src[0] instanceof PlayerUIInventory && $src[1] === UIInventorySlotOffset::CREATED_ITEM_OUTPUT){
					$tookTradePreview = true;
				}

				if($src === null || $dst === null || !self::applyTransfer($src[0], $src[1], $dst[0], $dst[1], $action->getCount())){
					$ok = false;
				}
			}elseif($action instanceof SwapStackRequestAction){
				$a = self::resolve($player, $action->getSlot1());
				$b = self::resolve($player, $action->getSlot2());

				if($a === null || $b === null){
					$ok = false;
				}else{
					$tmp = $a[0]->getItem($a[1]);
					$a[0]->setItem($a[1], $b[0]->getItem($b[1]));
					$b[0]->setItem($b[1], $tmp);
				}
			}elseif($action instanceof DropStackRequestAction){
				$src = self::resolve($player, $action->getSource());
				if($src === null){
					$ok = false;
				}else{
					$item = $src[0]->getItem($src[1]);
					if($item->isNull()){
						$ok = false;
					}else{
						$dropCount = $action->getCount() <= 0 ? 1 : min($action->getCount(), $item->getCount());
						$dropped = $item->pop($dropCount);
						$rest = $item->getCount() > 0 ? $item : ItemFactory::get(Item::AIR, 0, 0);
						$src[0]->setItem($src[1], $rest);
						$player->dropItem($dropped);
					}
				}
			}elseif($action instanceof DestroyStackRequestAction){
				$src = self::resolve($player, $action->getSource());
				if($src === null){
					$ok = false;
				}else{
					$src[0]->setItem($src[1], ItemFactory::get(Item::AIR, 0, 0));
				}
			}elseif($action instanceof CraftingConsumeInputStackRequestAction){
				$src = self::resolve($player, $action->getSource());
				if($src === null){
					$ok = false;
				}elseif(!self::isTradeInputSlot($player, $src[0])){
					$item = $src[0]->getItem($src[1]);
					$count = $action->getCount() <= 0 ? 1 : $action->getCount();
					$rest = $item->getCount() - min($count, $item->getCount());
					$src[0]->setItem($src[1], $rest > 0 ? $item->setCount($rest) : ItemFactory::get(Item::AIR, 0, 0));
				}
			}elseif($action instanceof MineBlockStackRequestAction){
			}elseif($action instanceof CraftRecipeStackRequestAction){
				if(!self::applyTradeCraft($player, $action)){
					$ok = false;
				}
			}else{
				$ok = false;
			}
		}
		if($ok && !$hasTradeCraft && $tookTradePreview && $tradeWindow instanceof TradeInventory){
			if($tradeWindow->getVillager()->executeLegacyTrade($player, $tradeWindow) === null){
				$ok = false;
			}
		}
		return $ok;
	}

	private static function involvesTradeCraft(ItemStackRequest $request) : bool{
		foreach($request->getActions() as $action){
			if($action instanceof CraftRecipeStackRequestAction && $action->getRecipeId() >= TradeOffer::RECIPE_BASE){
				return true;
			}
		}
		return false;
	}

	private static function applyTradeCraft(Player $player, CraftRecipeStackRequestAction $action) : bool{
		$window = $player->findWindow(TradeInventory::class);
		if(!$window instanceof TradeInventory){
			$player->getServer()->getLogger()->debug("Trade craft from " . $player->getName() . " rejected: no trade window open");
			return false;
		}

		$villager = $window->getVillager();
		if($villager->isClosed() || !$villager->isAlive() || $villager->distance($player) > 8){
			$player->getServer()->getLogger()->debug("Trade craft from " . $player->getName() . " rejected: villager unavailable");
			return false;
		}

		return $villager->executeTrade($player, $window, $action->getRecipeId(), $action->getRepetitions()) !== null;
	}

	/** @return array{BaseInventory, int}|null */
	private static function resolve(Player $player, ItemStackRequestSlotInfo $info) : ?array{
		$name = $info->getContainerName();
		$id = $name->getContainerId();
		$slot = $info->getSlotId();
		switch($id){
			case self::CONTAINER_HOTBAR_AND_INVENTORY:
				return [$player->getInventory(), $slot];
			case self::CONTAINER_HOTBAR:
				return [$player->getInventory(), $slot];
			case self::CONTAINER_INVENTORY:
				return [$player->getInventory(), 9 + $slot];
			case self::CONTAINER_ARMOR:
				return [$player->getArmorInventory(), $slot];
			case self::CONTAINER_CURSOR:
				return [$player->getCursorInventory(), 0];
			case self::CONTAINER_OFFHAND:
				return [$player->getOffHandInventory(), 0];
			case self::CONTAINER_CREATIVE_OUTPUT:
			case self::CONTAINER_TRADE_RESULT:
			case self::CONTAINER_TRADE2_RESULT:
				return [$player->getUIInventory(), UIInventorySlotOffset::CREATED_ITEM_OUTPUT];
			case self::CONTAINER_TRADE_INGREDIENT_1:
			case self::CONTAINER_TRADE2_INGREDIENT_1:
				return self::tradeSlot($player, TradeInventory::SLOT_INPUT_A);
			case self::CONTAINER_TRADE_INGREDIENT_2:
			case self::CONTAINER_TRADE2_INGREDIENT_2:
				return self::tradeSlot($player, TradeInventory::SLOT_INPUT_B);
			case self::CONTAINER_CONTAINER:
			case self::CONTAINER_DYNAMIC:
				$dynamicId = $name->getDynamicId();
				if($dynamicId !== null){
					$window = $player->getWindow($dynamicId);
					return $window instanceof BaseInventory ? [$window, $slot] : null;
				}
				return null;
			default:
				$player->getServer()->getLogger()->debug("ItemStackRequest from " . $player->getName() . ": unmapped container " . $id . " slot " . $slot);
				return null;
		}
	}

	/** @return array{BaseInventory, int}|null */
	private static function tradeSlot(Player $player, int $slot) : ?array{
		$window = $player->findWindow(TradeInventory::class);
		return $window instanceof TradeInventory ? [$window, $slot] : null;
	}

	private static function isTradeInputSlot(Player $player, BaseInventory $inventory) : bool{
		$window = $player->findWindow(TradeInventory::class);
		return $window instanceof TradeInventory && $window === $inventory;
	}

	private static function applyTransfer(BaseInventory $src, int $srcSlot, BaseInventory $dst, int $dstSlot, int $count) : bool{
		if($srcSlot < 0 || $dstSlot < 0 || $srcSlot >= $src->getSize() || $dstSlot >= $dst->getSize()){
			return false;
		}
		$item = $src->getItem($srcSlot);
		if($item->isNull()){
			return false;
		}
		$take = $count <= 0 ? $item->getCount() : min($count, $item->getCount());
		$taken = $item->pop($take);
		$src->setItem($srcSlot, $item->getCount() > 0 ? $item : ItemFactory::get(Item::AIR, 0, 0));

		$existing = $dst->getItem($dstSlot);
		if($existing->isNull()){
			$dst->setItem($dstSlot, $taken);
			return true;
		}
		if($taken->equals($existing) && $existing->getCount() < $existing->getMaxStackSize()){
			$space = $existing->getMaxStackSize() - $existing->getCount();
			$add = min($space, $taken->getCount());
			$existing->setCount($existing->getCount() + $add);
			$dst->setItem($dstSlot, $existing);
			$remaining = $taken->getCount() - $add;
			if($remaining > 0){
				self::putBack($src, $srcSlot, $taken->setCount($remaining));
			}
			return true;
		}
		self::putBack($src, $srcSlot, $taken);
		$tmp = $src->getItem($srcSlot);
		$src->setItem($srcSlot, $dst->getItem($dstSlot));
		$dst->setItem($dstSlot, $tmp);
		return true;
	}

	private static function putBack(BaseInventory $inventory, int $slot, Item $item) : void{
		$current = $inventory->getItem($slot);
		if($current->isNull()){
			$inventory->setItem($slot, $item);
		}elseif($current->equals($item)){
			$current->setCount($current->getCount() + $item->getCount());
			$inventory->setItem($slot, $current);
		}else{
			$inventory->setItem($slot, $item);
		}
	}

	private static function resync(Player $player) : void{
		$player->getInventory()->sendContents($player);
		$player->getCursorInventory()->sendContents($player);
		$player->getUIInventory()->sendContents($player);
		$player->getArmorInventory()->sendContents($player);
		$player->getOffHandInventory()->sendContents($player);
		$window = $player->findWindow(TradeInventory::class);
		if($window instanceof TradeInventory){
			$window->sendContents($player);
			$window->refreshPreview();
		}
	}
}
