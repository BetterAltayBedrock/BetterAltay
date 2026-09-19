<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\FurnaceOptions;

class SetPlayerFurnaceOptionsPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::SET_PLAYER_FURNACE_OPTIONS_PACKET;

	public int $furnaceType;
	public FurnaceOptions $furnaceOptions;

	protected function decodePayload() : void{
		$this->furnaceType = $this->getByte();
		$this->furnaceOptions = FurnaceOptions::read($this);
	}

	protected function encodePayload() : void{
		$this->putByte($this->furnaceType);
		$this->furnaceOptions->write($this);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleSetPlayerFurnaceOptions($this);
	}
}