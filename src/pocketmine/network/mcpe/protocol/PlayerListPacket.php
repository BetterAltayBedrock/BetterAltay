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

use InvalidArgumentException;
use UnexpectedValueException as PacketDecodeException;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\utils\Color;
use function count;
use function rand;

class PlayerListPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_LIST_PACKET;

	public const TYPE_ADD = 1;
	public const TYPE_REMOVE = 0;

	/** @var PlayerListEntry[] */
	public array $entries = [];

	public function clean() : PlayerListPacket{
		$this->entries = [];
		return parent::clean();
	}

	protected function decodePayload() : void{
		$count = $this->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			$entry = new PlayerListEntry();
			$entry->type = $this->getUnsignedVarInt();
			$this->getByte(); // legacyId

			if($entry->type === self::TYPE_ADD){
				$entry->uuid = $this->getUUID();
				$entry->entityUniqueId = $this->getEntityUniqueId();
				$entry->username = $this->getString();
				$entry->xboxUserId = $this->getString();
				$entry->playFabId = $this->getString();
				$entry->platformChatId = $this->getString();
				$entry->buildPlatform = $this->getLInt();
				$entry->skinData = $this->getSkin();
				$entry->isTeacher = $this->getBool();
				$entry->isHost = $this->getBool();
				$entry->isSubClient = $this->getBool();
				$entry->color = Color::fromARGB($this->getLInt());
			}elseif($entry->type === self::TYPE_REMOVE){
				$entry->uuid = $this->getUUID();
			}else{
				throw new PacketDecodeException("Unknown player list entry type " . $entry->type);
			}

			$this->entries[$i] = $entry;
		}
	}

	protected function encodePayload() : void{
		$this->putUnsignedVarInt(count($this->entries));
		foreach($this->entries as $entry){
			$this->putUnsignedVarInt($entry->type);
			$this->putByte($entry->type === self::TYPE_ADD ? 0 : 1);

			if($entry->type === self::TYPE_ADD){
				$this->putUUID($entry->uuid);
				$this->putEntityUniqueId($entry->entityUniqueId);
				$this->putString($entry->username);
				$this->putString($entry->xboxUserId);
				$this->putString($entry->playFabId);
				$this->putString($entry->platformChatId);
				$this->putLInt($entry->buildPlatform);
				$this->putSkin($entry->skinData);
				$this->putBool($entry->isTeacher);
				$this->putBool($entry->isHost);
				$this->putBool($entry->isSubClient);
				$this->putLInt(($entry->color ?? new Color(rand(0, 255), rand(0, 255), rand(0, 255)))->toARGB());
			}elseif($entry->type === self::TYPE_REMOVE){
				$this->putUUID($entry->uuid);
			}else{
				throw new InvalidArgumentException("Unknown player list entry type " . $entry->type);
			}
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handlePlayerList($this);
	}
}
