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

namespace pocketmine\inventory;

use pocketmine\entity\passive\Villager;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\Player;

class TradeInventory extends ContainerInventory implements FakeInventory{

	public const SLOT_INPUT_A = 0;
	public const SLOT_INPUT_B = 1;

	/** @var Villager */
	private $villager;

	public function __construct(Villager $villager){
		$this->villager = $villager;
		parent::__construct($villager);
	}

	public function getVillager() : Villager{
		return $this->villager;
	}

	public function getNetworkType() : int{
		return WindowTypes::TRADING;
	}

	public function getName() : string{
		return "Trading";
	}

	public function getDefaultSize() : int{
		return 2;
	}

	public function getUIOffsets() : array{
		return UIInventorySlotOffset::TRADE2_INGREDIENT + UIInventorySlotOffset::TRADE_INGREDIENT;
	}

	public function onOpen(Player $who) : void{
		parent::onOpen($who);

		$pk = new UpdateTradePacket();
		$pk->windowId = $who->getWindowId($this);
		$pk->windowType = WindowTypes::TRADING;
		$pk->windowSlotCount = count($this->villager->getRecipes());
		$pk->tradeTier = $this->villager->getTradeTier();
		$pk->traderEid = $this->villager->getId();
		$pk->playerEid = $who->getId();
		$pk->displayName = $this->villager->getDisplayName();
		$pk->isV2Trading = true;
		$pk->isWilling = $this->villager->isWilling();
		$encodedOffers = (new NetworkLittleEndianNBTStream())->write($this->villager->getOffersTag());
		$pk->offers = $encodedOffers === false ? "" : $encodedOffers;
		$who->dataPacket($pk);

		$this->refreshPreview();
	}

	public function setItem(int $index, Item $item, bool $send = true) : bool{
		if(!parent::setItem($index, $item, $send)){
			return false;
		}
		$this->refreshPreview();
		return true;
	}

	public function refreshPreview() : void{
		$offer = $this->villager->findSatisfiedOffer($this);
		$result = $offer !== null ? $offer->getSell()->toItem() : ItemFactory::get(Item::AIR, 0, 0);
		foreach($this->getViewers() as $viewer){
			if($viewer instanceof Player){
				$ui = $viewer->getUIInventory();
				if(!$result->isNull()){
					// clear first so the client refreshes even when the same item was already shown
					$ui->setItem(UIInventorySlotOffset::CREATED_ITEM_OUTPUT, ItemFactory::get(Item::AIR, 0, 0));
				}
				$ui->setItem(UIInventorySlotOffset::CREATED_ITEM_OUTPUT, $result);
			}
		}
	}

	public function returnInputs(Player $who) : void{
		foreach($this->getContents() as $item){
			if($item->isNull()){
				continue;
			}
			if(!$who->isAlive() || $who->isClosed()){
				$who->dropItem($item);
				continue;
			}
			foreach($who->getInventory()->addItem($item) as $leftover){
				$who->dropItem($leftover);
			}
		}
		$this->clearAll(false);
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);

		$this->returnInputs($who);
		$ui = $who->getUIInventory();
		foreach($this->getUIOffsets() as $uiSlot => $_){
			$item = $ui->getItem($uiSlot);
			if($item->isNull()){
				continue;
			}
			if(!$who->isAlive() || $who->isClosed()){
				$who->dropItem($item);
			}else{
				foreach($who->getInventory()->addItem($item) as $leftover){
					$who->dropItem($leftover);
				}
			}
			$ui->setItem($uiSlot, ItemFactory::get(Item::AIR, 0, 0));
		}
		$ui->setItem(UIInventorySlotOffset::CREATED_ITEM_OUTPUT, ItemFactory::get(Item::AIR, 0, 0));

		if($this->villager->getTradingPlayer() === $who){
			$this->villager->setTradingPlayer(null);
		}
	}
}
