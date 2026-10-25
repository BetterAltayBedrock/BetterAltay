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

namespace pocketmine\network\mcpe\protocol\types;

use InvalidArgumentException;
use InvalidStateException;
use pocketmine\inventory\EnchantInventory;
use pocketmine\inventory\FakeInventory;
use pocketmine\inventory\FakeResultInventory;
use pocketmine\inventory\PlayerUIInventory;
use pocketmine\inventory\TradeInventory;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\inventory\transaction\action\EnchantAction;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\Player;
use pocketmine\utils\Binary;
use UnexpectedValueException;

class NetworkInventoryAction{
	public const SOURCE_CONTAINER = 0;
	public const SOURCE_GLOBAL_INVENTORY = 1;
	public const SOURCE_WORLD = 2; //drop/pickup item entity
	public const SOURCE_CREATIVE = 3;
	public const SOURCE_UNTRACKED_INTERACTION_UI = 100;
	public const SOURCE_TODO = 99999;

	/**
	 * Fake window IDs for the SOURCE_TODO type (99999)
	 *
	 * These identifiers are used for inventory source types which are not currently implemented server-side in MCPE.
	 * As a general rule of thumb, anything that doesn't have a permanent inventory is client-side. These types are
	 * to allow servers to track what is going on in client-side windows.
	 *
	 * Expect these to change in the future.
	 */
	public const SOURCE_TYPE_CRAFTING_RESULT = -4;
	public const SOURCE_TYPE_CRAFTING_USE_INGREDIENT = -5;

	public const SOURCE_TYPE_FAKE_INVENTORY_INPUT = -10;
	public const SOURCE_TYPE_FAKE_INVENTORY_MATERIAL = -11;
	public const SOURCE_TYPE_FAKE_INVENTORY_RESULT = -12;

	public const SOURCE_TYPE_ENCHANT_OUTPUT = -17;

	public const SOURCE_TYPE_TRADING_INPUT_1 = -20;
	public const SOURCE_TYPE_TRADING_INPUT_2 = -21;
	public const SOURCE_TYPE_TRADING_USE_INPUTS = -22;
	public const SOURCE_TYPE_TRADING_OUTPUT = -23;

	public const SOURCE_TYPE_TRADE_COMPANION_INVENTORY = -30;
	public const SOURCE_TYPE_TRADE_COMPANION_UI = -31;

	/** Any client-side window dropping its contents when the player closes it */
	public const SOURCE_TYPE_CONTAINER_DROP_CONTENTS = -100;

	public const ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM = 0;
	public const ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM = 1;

	public const ACTION_MAGIC_SLOT_DROP_ITEM = 0;
	public const ACTION_MAGIC_SLOT_PICKUP_ITEM = 1;

	public int $sourceType;
	public ?int $windowId = null;
	public ?int $sourceFlags = 0;
	public int $inventorySlot;
	public ItemStackWrapper $oldItem;
	public ItemStackWrapper $newItem;

	/**
	 * @return $this
	 */
	public function read(NetworkBinaryStream $packet) : static{
		$this->sourceType = $packet->getUnsignedVarInt();
		if($packet->getBool()){
			$this->windowId = Binary::signByte($packet->getByte());
		}
		if($packet->getBool()){
			$this->sourceFlags = $packet->getUnsignedVarInt();
		}
		$this->inventorySlot = $packet->getUnsignedVarInt();
		$this->oldItem = ItemStackWrapper::read($packet, true);
		$this->newItem = ItemStackWrapper::read($packet, true);

		return $this;
	}

	/**
	 *
	 * @param NetworkBinaryStream $packet
	 *
	 * @return void
	 */
	public function write(NetworkBinaryStream $packet) : void{
		$packet->putUnsignedVarInt($this->sourceType);
		$packet->putBool($this->windowId !== null);
		$packet->putByte($this->windowId);
		$packet->putBool($this->sourceFlags !== null);
		$packet->putUnsignedVarInt($this->sourceFlags);
		$packet->putUnsignedVarInt($this->inventorySlot);
		$this->oldItem->write($packet, true);
		$this->newItem->write($packet, true);
	}

	/**
	 * @return InventoryAction|null
	 *
	 * @throws UnexpectedValueException
	 */
	public function createInventoryAction(Player $player){
		$oldItem = $this->oldItem->getItemStack();
		$newItem = $this->newItem->getItemStack();
		if($oldItem->equalsExact($newItem)){
			//filter out useless noise in 1.13
			return null;
		}
		switch($this->sourceType){
			case self::SOURCE_CONTAINER:
				$window = $player->getWindow($this->windowId);
				if($window !== null){
					if($window instanceof PlayerUIInventory and $this->inventorySlot > 0){
						// TODO: HACK! dont rely on client due to creation of new item with result inventory actions
						$oldItem = $window->getItem($this->inventorySlot);
					}

					return new SlotChangeAction($window, $this->inventorySlot, $oldItem, $newItem);
				}

				throw new UnexpectedValueException("Player " . $player->getName() . " has no open container with window ID $this->windowId");
			case self::SOURCE_WORLD:
				if($this->inventorySlot !== self::ACTION_MAGIC_SLOT_DROP_ITEM){
					throw new UnexpectedValueException("Only expecting drop-item world actions from the client!");
				}

				return new DropItemAction($newItem);
			case self::SOURCE_CREATIVE:
				$type = match ($this->inventorySlot) {
					self::ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM => CreativeInventoryAction::TYPE_DELETE_ITEM,
					self::ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM => CreativeInventoryAction::TYPE_CREATE_ITEM,
					default => throw new UnexpectedValueException("Unexpected creative action type $this->inventorySlot"),
				};

				return new CreativeInventoryAction($oldItem, $newItem, $type);
			case self::SOURCE_UNTRACKED_INTERACTION_UI:
			case self::SOURCE_TODO:
				$window = $player->findWindow(FakeInventory::class);

				switch($this->windowId){
					case self::SOURCE_TYPE_CONTAINER_DROP_CONTENTS: //TODO: this type applies to all fake windows, not just crafting
						return new SlotChangeAction($window ?? $player->getCraftingGrid(), $this->inventorySlot, $oldItem, $newItem);
					case self::SOURCE_TYPE_CRAFTING_RESULT:
					case self::SOURCE_TYPE_CRAFTING_USE_INGREDIENT:
						return null;
					case self::SOURCE_TYPE_ENCHANT_OUTPUT:
						if($window instanceof EnchantInventory){
							return new EnchantAction($window, $this->inventorySlot, $oldItem, $newItem);
						}else{
							if($window === null){
								throw new InvalidStateException("Window not found");
							}else{
								throw new InvalidStateException("Unexpected fake inventory given. Expected " . EnchantInventory::class . " , given " . get_class($window));
							}
						}
					case self::SOURCE_TYPE_TRADE_COMPANION_INVENTORY:
					    return null;
					case self::SOURCE_TYPE_TRADE_COMPANION_UI:
						return null;
					case self::SOURCE_TYPE_TRADING_INPUT_1:
					case self::SOURCE_TYPE_TRADING_INPUT_2:
					case self::SOURCE_TYPE_TRADING_USE_INPUTS:
						return null;
					case self::SOURCE_TYPE_TRADING_OUTPUT:
						$tradeWindow = $player->findWindow(TradeInventory::class);
						if($tradeWindow instanceof TradeInventory){
							$oldItem = $player->getUIInventory()->getItem(UIInventorySlotOffset::CREATED_ITEM_OUTPUT);
							return new SlotChangeAction($player->getUIInventory(), UIInventorySlotOffset::CREATED_ITEM_OUTPUT, $oldItem, $newItem);
						}
						throw new UnexpectedValueException("Player " . $player->getName() . " has no open trade window for trading output");
					case self::SOURCE_TYPE_FAKE_INVENTORY_INPUT:
					case self::SOURCE_TYPE_FAKE_INVENTORY_MATERIAL:
						return null; // useless noise
					case self::SOURCE_TYPE_FAKE_INVENTORY_RESULT:
						if($window instanceof FakeResultInventory){
							if(!$window->onResult($player, $oldItem)){
								throw new InvalidStateException("Output doesnt match for Player " . $player->getName() . " in " . get_class($window));
							}
						}

						return null;
				}

				throw new UnexpectedValueException("Player " . $player->getName() . " has no open container with window ID $this->windowId");
			default:
				throw new UnexpectedValueException("Unknown inventory source type $this->sourceType");
		}
	}
}
