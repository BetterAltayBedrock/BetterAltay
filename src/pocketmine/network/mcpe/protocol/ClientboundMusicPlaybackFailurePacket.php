<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\MusicPlaybackFailureEntry;
use UnexpectedValueException;
use function count;

final class ClientboundMusicPlaybackFailurePacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MUSIC_PLAYBACK_FAILURE_PACKET;

	/** @var MusicPlaybackFailureEntry[] */
	private array $failures;

	/**
	 * @generate-create-func
	 * @param MusicPlaybackFailureEntry[] $failures
	 */
	public static function create(array $failures) : self{
		$result = new self;
		$result->failures = $failures;
		return $result;
	}

	/** @return MusicPlaybackFailureEntry[] */
	public function getFailures() : array{
		return $this->failures;
	}

	protected function decodePayload() : void{
		$this->failures = [];
		$failureCount = $this->getUnsignedVarInt();
		if($failureCount > 28){
			throw new UnexpectedValueException("Too many failures in ClientboundMusicPlaybackFailurePacket");
		}
		for($i = 0; $i < $failureCount; ++$i){
			$this->failures[] = MusicPlaybackFailureEntry::read($this);
		}
	}

	protected function encodePayload() : void{
		$this->putUnsignedVarInt(count($this->failures));
		foreach($this->failures as $failure){
			$failure->write($this);
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleClientboundMusicPlaybackFailure($this);
	}
}
