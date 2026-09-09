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

namespace pocketmine\network\mcpe\protocol\types\inventory;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBinaryStream as PacketSerializer;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;

class ReleaseItemTransactionData extends TransactionData{
	public const ACTION_RELEASE = 0; //bow shoot
	public const ACTION_CONSUME = 1; //eat food, drink potion

	/** @var int */
	private $actionType;
	/** @var int */
	private $hotbarSlot;
	/** @var ItemStackWrapper */
	private $itemInHand;
	/** @var Vector3 */
	private $headPos;
	private int $handSlot;

	public function getActionType() : int{
		return $this->actionType;
	}

	public function getHotbarSlot() : int{
		return $this->hotbarSlot;
	}

	public function getItemInHand() : ItemStackWrapper{
		return $this->itemInHand;
	}

	public function getHeadPos() : Vector3{
		return $this->headPos;
	}

	public function getHandSlot() : int{
		return $this->handSlot;
	}

	public function getTypeId() : int{
		return InventoryTransactionPacket::TYPE_RELEASE_ITEM;
	}

	protected function decodeData(PacketSerializer $stream) : void{
		$this->actionType = $stream->getVarInt();
		$this->hotbarSlot = $stream->getVarInt();
		$this->itemInHand = ItemStackWrapper::read($stream, true);
		$this->headPos = $stream->getVector3();
		$this->handSlot = $stream->getByte();
	}

	protected function encodeData(PacketSerializer $stream) : void{
		$stream->putVarInt($this->actionType);
		$stream->putVarInt($this->hotbarSlot);
		$this->itemInHand->write($stream, true);
		$stream->putVector3($this->headPos);
		$stream->putByte($this->handSlot);
	}

	/**
	 * @param NetworkInventoryAction[] $actions
	 */
	public static function new(array $actions, int $actionType, int $hotbarSlot, ItemStackWrapper $itemInHand, Vector3 $headPos, int $handSlot) : self{
		$result = new self;
		$result->actions = $actions;
		$result->actionType = $actionType;
		$result->hotbarSlot = $hotbarSlot;
		$result->itemInHand = $itemInHand;
		$result->headPos = $headPos;
		$result->handSlot = $handSlot;

		return $result;
	}
}
