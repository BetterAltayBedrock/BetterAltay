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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession as PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\types\inventory\InventoryTransactionChangedSlotsHack;
use pocketmine\network\mcpe\protocol\types\inventory\MismatchTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\TransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use UnexpectedValueException as PacketDecodeException;
use function count;

class InventoryTransactionPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::INVENTORY_TRANSACTION_PACKET;

	public const TYPE_NORMAL = 0;
	public const TYPE_MISMATCH = 1;
	public const TYPE_USE_ITEM = 2;
	public const TYPE_USE_ITEM_ON_ENTITY = 3;
	public const TYPE_RELEASE_ITEM = 4;

	public int $requestId;
	/** @var InventoryTransactionChangedSlotsHack[] */
	public array $requestChangedSlots;
	public TransactionData $trData;

	protected function decodePayload() : void{
		$this->requestId = $this->readGenericTypeNetworkId();
		$this->requestChangedSlots = [];
		if($this->getBool()){ //hasChangedSlots
			for($i = 0, $len = $this->getUnsignedVarInt(); $i < $len; ++$i){
				$this->requestChangedSlots[] = InventoryTransactionChangedSlotsHack::read($this);
			}
		}

		$transactionType = $this->getUnsignedVarInt();

		$this->trData = match ($transactionType) {
			self::TYPE_NORMAL => new NormalTransactionData(),
			self::TYPE_MISMATCH => new MismatchTransactionData(),
			self::TYPE_USE_ITEM => new UseItemTransactionData(),
			self::TYPE_USE_ITEM_ON_ENTITY => new UseItemOnEntityTransactionData(),
			self::TYPE_RELEASE_ITEM => new ReleaseItemTransactionData(),
			default => throw new PacketDecodeException("Unknown transaction type $transactionType"),
		};

		$this->trData->decode($this);
	}

	protected function encodePayload() : void{
		$this->writeGenericTypeNetworkId($this->requestId);
		$this->putBool($this->requestId !== 0);
		if($this->requestId !== 0){
			$this->putUnsignedVarInt(count($this->requestChangedSlots));
			foreach($this->requestChangedSlots as $changedSlots){
				$changedSlots->write($this);
			}
		}


		$this->putUnsignedVarInt($this->trData->getTypeId());
		$this->trData->encode($this);
	}

	public function handle(PacketHandlerInterface $session) : bool{
		return $session->handleInventoryTransaction($this);
	}
}
