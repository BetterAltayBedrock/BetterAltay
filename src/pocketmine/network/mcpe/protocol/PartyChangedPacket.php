<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;

class PartyChangedPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::PARTY_CHANGED_PACKET;

	public ?string $partyId = null;
	public ?bool $partyLeader = null;

	protected function decodePayload() : void{
		if($this->getBool()){
			$this->partyId = $this->getString();
			$this->partyLeader = $this->getBool();
		}
	}

	protected function encodePayload() : void{
		$hasPartyInfo = $this->partyId !== null && $this->partyLeader !== null;
		$this->putBool($hasPartyInfo);
		if($hasPartyInfo){
			$this->putString($this->partyId);
			$this->putBool($this->partyLeader);
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handlePartyChanged($this);
	}
}