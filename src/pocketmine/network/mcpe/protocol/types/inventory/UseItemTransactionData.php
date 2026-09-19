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

class UseItemTransactionData extends TransactionData{
	public const ACTION_CLICK_BLOCK = 0;
	public const ACTION_CLICK_AIR = 1;
	public const ACTION_BREAK_BLOCK = 2;
	public const ACTION_USE_AS_ATTACK = 3;

	public const TRIGGER_UNKNOWN = 0;
	public const TRIGGER_PLAYER_INPUT = 1;
	public const TRIGGER_SIMULATION_TICK = 2;

	public const CLIENT_PREDICTION_FAILURE = 0;
	public const CLIENT_PREDICTION_SUCCESS = 1;

	private int $actionType;
	private int $triggerType;
	private Vector3 $blockPos;
	private int $face;
	private int $hotbarSlot;
	private int $handSlot;
	private ItemStackWrapper $itemInHand;
	private Vector3 $playerPos;
	private Vector3 $clickPos;
	private int $blockRuntimeId;
	private int $clientPrediction;
	private int $clientCooldownState;

	public function getActionType() : int{
		return $this->actionType;
	}

	public function getTriggerType() : int{
		return $this->triggerType;
	}

	public function getBlockPos() : Vector3{
		return $this->blockPos;
	}

	public function getFace() : int{
		return $this->face;
	}

	public function getHotbarSlot() : int{
		return $this->hotbarSlot;
	}

	public function getHandSlot() : int{
		return $this->handSlot;
	}

	public function getItemInHand() : ItemStackWrapper{
		return $this->itemInHand;
	}

	public function getPlayerPos() : Vector3{
		return $this->playerPos;
	}

	public function getClickPos() : Vector3{
		return $this->clickPos;
	}

	public function getBlockRuntimeId() : int{
		return $this->blockRuntimeId;
	}

	public function getClientPrediction() : int{
		return $this->clientPrediction;
	}

	public function getClientCooldownState() : int{
		return $this->clientCooldownState;
	}

	public function getTypeId() : int{
		return InventoryTransactionPacket::TYPE_USE_ITEM;
	}

	protected function decodeData(PacketSerializer $stream) : void{
		$this->actionType = $stream->getVarInt();
		$this->triggerType = $stream->getByte();
		$x = $y = $z = 0;
		$stream->getBlockPosition($x, $y, $z);
		$this->blockPos = new Vector3($x, $y, $z);
		$this->face = $stream->getByte();
		$this->hotbarSlot = $stream->getVarInt();
		$this->handSlot = $stream->getByte();
		$this->itemInHand = ItemStackWrapper::read($stream, true);
		$this->playerPos = $stream->getVector3();
		$this->clickPos = $stream->getVector3();
		$this->blockRuntimeId = $stream->getUnsignedVarInt();
		$this->clientPrediction = $stream->getByte();
		$this->clientCooldownState = $stream->getByte();
	}

	protected function encodeData(PacketSerializer $stream) : void{
		$stream->putVarInt($this->actionType);
		$stream->putByte($this->triggerType);
		$stream->putBlockPosition($this->blockPos->x, $this->blockPos->y, $this->blockPos->z);
		$stream->putByte($this->face);
		$stream->putVarInt($this->hotbarSlot);
		$stream->putByte($this->handSlot);
		$this->itemInHand->write($stream, true);
		$stream->putVector3($this->playerPos);
		$stream->putVector3($this->clickPos);
		$stream->putUnsignedVarInt($this->blockRuntimeId);
		$stream->putByte($this->clientPrediction);
		$stream->putByte($this->clientCooldownState);
	}

	/**
	 * @param NetworkInventoryAction[] $actions
	 */
	public static function new(array $actions, int $actionType, int $triggerType, Vector3 $blockPos, int $face, int $hotbarSlot, int $handSlot, ItemStackWrapper $itemInHand, Vector3 $playerPos, Vector3 $clickPos, int $blockRuntimeId, int $clientPrediction, int $clientCooldownState) : self{
		$result = new self;
		$result->actions = $actions;
		$result->actionType = $actionType;
		$result->triggerType = $triggerType;
		$result->blockPos = $blockPos;
		$result->face = $face;
		$result->hotbarSlot = $hotbarSlot;
		$result->handSlot = $handSlot;
		$result->itemInHand = $itemInHand;
		$result->playerPos = $playerPos;
		$result->clickPos = $clickPos;
		$result->blockRuntimeId = $blockRuntimeId;
		$result->clientPrediction = $clientPrediction;
		$result->clientCooldownState = $clientCooldownState;
		return $result;
	}
}
