<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\SoundData;

class ClientboundUpdateSoundDataPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_UPDATE_SOUND_DATA_PACKET;

	public int $serverSoundHandle;
	public ?SoundData $stop = null;
	public ?SoundData $setVolume = null;
	public ?SoundData $setPitch = null;
	public ?SoundData $fade = null;
	public ?SoundData $seekTo = null;
	public ?SoundData $pause = null;
	public ?SoundData $resume = null;

	public static function create(
		int $serverSoundHandle,
		?SoundData $stop = null,
		?SoundData $setVolume = null,
		?SoundData $setPitch = null,
		?SoundData $fade = null,
		?SoundData $seekTo = null,
		?SoundData $pause = null,
		?SoundData $resume = null
	) : self{
		$result = new self;
		$result->serverSoundHandle = $serverSoundHandle;
		$result->stop = $stop;
		$result->setVolume = $setVolume;
		$result->setPitch = $setPitch;
		$result->fade = $fade;
		$result->seekTo = $seekTo;
		$result->pause = $pause;
		$result->resume = $resume;
		return $result;
	}

	protected function decodePayload() : void{
		$this->serverSoundHandle = $this->getLLong();
		$this->stop = $this->readOptional(fn() => SoundData::read($this));
		$this->setVolume = $this->readOptional(fn() => SoundData::read($this));
		$this->setPitch = $this->readOptional(fn() => SoundData::read($this));
		$this->fade = $this->readOptional(fn() => SoundData::read($this));
		$this->seekTo = $this->readOptional(fn() => SoundData::read($this));
		$this->pause = $this->readOptional(fn() => SoundData::read($this));
		$this->resume = $this->readOptional(fn() => SoundData::read($this));
	}

	protected function encodePayload() : void{
		$this->putLLong($this->serverSoundHandle);
		$this->writeOptional($this->stop, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->setVolume, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->setPitch, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->fade, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->seekTo, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->pause, fn(SoundData $data) => $data->write($this));
		$this->writeOptional($this->resume, fn(SoundData $data) => $data->write($this));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleClientboundUpdateSoundData($this);
	}
}