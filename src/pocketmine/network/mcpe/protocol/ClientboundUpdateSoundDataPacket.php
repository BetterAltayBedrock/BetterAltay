<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\SoundData;

class ClientboundUpdateSoundDataPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_UPDATE_SOUND_DATA_PACKET;

	public int $serverSoundHandle;
	public SoundData $event;

	public static function create(
		int $serverSoundHandle,
		SoundData $event,
	) : self{
		$result = new self;
		$result->serverSoundHandle = $serverSoundHandle;
		$result->event = $event;
		return $result;
	}

	protected function decodePayload() : void{
		$this->serverSoundHandle = $this->getLLong();
		$this->event = SoundData::read($this);
	}

	protected function encodePayload() : void{
		$this->putLLong($this->serverSoundHandle);
		$this->event->write($this);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleClientboundUpdateSoundData($this);
	}
}