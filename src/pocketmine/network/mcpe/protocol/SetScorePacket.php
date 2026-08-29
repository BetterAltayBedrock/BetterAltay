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
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use UnexpectedValueException as PacketDecodeException;
use function count;

class SetScorePacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::SET_SCORE_PACKET;

	/** @var ScorePacketEntry[] */
	public array $entries = [];

	protected function decodePayload() : void{
		$count = $this->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			$entry = new ScorePacketEntry();
			$entry->type = $this->getUnsignedVarInt();
			$this->getString();
			$entry->scoreboardId = $this->getVarLong();

			switch($entry->type){
				case ScorePacketEntry::TYPE_REMOVE:
					$entry->objectiveName = $this->readOptional(fn() => $this->getString());
					break;
				case ScorePacketEntry::TYPE_PLAYER:
				case ScorePacketEntry::TYPE_ENTITY:
					$entry->objectiveName = $this->getString();
					$entry->score = $this->getLInt();
					$entry->entityUniqueId = $this->getEntityUniqueId();
					break;
				case ScorePacketEntry::TYPE_FAKE_PLAYER:
					$entry->objectiveName = $this->getString();
					$entry->score = $this->getLInt();
					$entry->customName = $this->getString();
					break;
				default:
					throw new PacketDecodeException("Unknown entry type $entry->type");
			}
			$this->entries[] = $entry;
		}
	}

	protected function encodePayload() : void{
		$this->putUnsignedVarInt(count($this->entries));
		foreach($this->entries as $entry){
			$this->putUnsignedVarInt($entry->type);
			$this->putString(match ($entry->type) {
				ScorePacketEntry::TYPE_REMOVE => "remove",
				ScorePacketEntry::TYPE_PLAYER => "changeplayer",
				ScorePacketEntry::TYPE_ENTITY => "changeentity",
				ScorePacketEntry::TYPE_FAKE_PLAYER => "changefakeplayer",
				default => throw new InvalidArgumentException("Unknown type $entry->type")
			});

			$this->putVarLong($entry->scoreboardId);
			$objectiveName = $entry->objectiveName === "" ? " " : $entry->objectiveName;
			switch($entry->type){
				case ScorePacketEntry::TYPE_REMOVE:
					$this->writeOptional($objectiveName, fn($objectiveName) => $this->putString($objectiveName));
					break;
				case ScorePacketEntry::TYPE_PLAYER:
				case ScorePacketEntry::TYPE_ENTITY:
					$this->putString($objectiveName ?? throw new InvalidArgumentException("Objective name must be set for player/entity entry"));
					$this->putLInt($entry->score);
					$this->putEntityUniqueId($entry->entityUniqueId);
					break;
				case ScorePacketEntry::TYPE_FAKE_PLAYER:
					$this->putString($objectiveName ?? throw new InvalidArgumentException("Objective name must be set for fake player entry"));
					$this->putLInt($entry->score);
					$this->putString($entry->customName === "" ? " " : $entry->customName);
					break;
				default:
					throw new InvalidArgumentException("Unknown entry type $entry->type");
			}
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleSetScore($this);
	}
}
