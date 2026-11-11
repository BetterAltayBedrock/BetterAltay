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

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\GatheringJoinInfo;
use pocketmine\network\mcpe\protocol\types\TransferPlayerUIOptions;

class TransferPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::TRANSFER_PACKET;

	public string $address;
	public int $port = 19132;
	public bool $reloadWorld = false; //always false
	public ?GatheringJoinInfo $gatheringJoinInfo = null;
	public ?TransferPlayerUIOptions $serverTransferSettings = null;

	protected function decodePayload() : void{
		$this->address = $this->getString();
		$this->port = $this->getLShort();
		$this->reloadWorld = $this->getBool();
		$this->gatheringJoinInfo = $this->readOptional(fn() => GatheringJoinInfo::read($this));
		$this->serverTransferSettings = $this->readOptional(fn() => TransferPlayerUIOptions::read($this));
	}

	protected function encodePayload() : void{
		$this->putString($this->address);
		$this->putLShort($this->port);
		$this->putBool($this->reloadWorld);
		$this->writeOptional($this->gatheringJoinInfo, fn(GatheringJoinInfo $gatheringJoinInfo) => $gatheringJoinInfo->write($this));
		$this->writeOptional($this->serverTransferSettings, fn(TransferPlayerUIOptions $settings) => $settings->write($this));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleTransfer($this);
	}
}
