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

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\network\mcpe\protocol\types\entityProperty\EntityProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\utils\UUID;
use function count;
use function is_null;

class AddPlayerPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::ADD_PLAYER_PACKET;

	public UUID $uuid;
	public string $username;
	public ?int $entityUniqueId = null; //TODO
	public int $entityRuntimeId;
	public string $platformChatId = "";
	public Vector3 $position;
	public ?Vector3 $motion = null;
	public float $pitch = 0.0;
	public float $yaw = 0.0;
	public ?float $headYaw = null; //TODO
	public ItemStackWrapper $item;
	/**
	 * @var mixed[][]
	 * @phpstan-var array<int, array{0: int, 1: mixed}>
	 */
	public array $metadata = [];

	public ?EntityProperties $entityProperties = null;

	public ?UpdateAbilitiesPacket $abilitiesPacket = null;

	public int $gameMode = 0;
	public array $links = [];

	public string $deviceId = ""; //TODO: fill player's device ID (???)
	public int $buildPlatform = DeviceOS::ANDROID;

	protected function decodePayload() : void{
		$this->uuid = $this->getUUID();
		$this->username = $this->getString();
		$this->entityRuntimeId = $this->getEntityRuntimeId();
		$this->platformChatId = $this->getString();
		$this->position = $this->getVector3();
		$this->motion = $this->getVector3();
		$this->pitch = $this->getLFloat();
		$this->yaw = $this->getLFloat();
		$this->headYaw = $this->getLFloat();
		$this->item = ItemStackWrapper::read($this, true);
		$this->gameMode = $this->getVarInt();
		$this->metadata = $this->getEntityMetadata();
		$this->entityProperties = EntityProperties::readFromPacket($this);
		$this->abilitiesPacket = new UpdateAbilitiesPacket($this->getRemaining());

		$linkCount = $this->getUnsignedVarInt();
		for($i = 0; $i < $linkCount; ++$i){
			$this->links[$i] = $this->getEntityLink();
		}

		$this->deviceId = $this->getString();
		$this->buildPlatform = $this->getLInt();
	}

	protected function encodePayload() : void{
		$this->putUUID($this->uuid);
		$this->putString($this->username);
		$this->putEntityRuntimeId($this->entityRuntimeId);
		$this->putString($this->platformChatId);
		$this->putVector3($this->position);
		$this->putVector3Nullable($this->motion);
		$this->putLFloat($this->pitch);
		$this->putLFloat($this->yaw);
		$this->putLFloat($this->headYaw ?? $this->yaw);
		$this->item->write($this, true);
		$this->putVarInt($this->gameMode);
		$this->putEntityMetadata($this->metadata);

		if(is_null($this->entityProperties)){
			$this->entityProperties = new EntityProperties();
		}
		$this->entityProperties->encode($this);

		if(is_null($this->abilitiesPacket)){
			$this->abilitiesPacket = UpdateAbilitiesPacket::makeDefaultAbilities($this->entityRuntimeId);
		}
		$this->abilitiesPacket->fastEncode();
		$this->put($this->abilitiesPacket->getBuffer());

		$this->putUnsignedVarInt(count($this->links));
		foreach($this->links as $link){
			$this->putEntityLink($link);
		}

		$this->putString($this->deviceId);
		$this->putLInt($this->buildPlatform);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleAddPlayer($this);
	}
}
