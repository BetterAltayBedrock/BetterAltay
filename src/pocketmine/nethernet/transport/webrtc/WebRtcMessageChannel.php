<?php

/*
 * This file is part of NetherNet.
 * Copyright (C) 2026 Axolotl Team <https://github.com/axolotl-pm/NetherNet>
 *
 * NetherNet is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\nethernet\transport\webrtc;

use pmmp\webrtc\DataChannel;
use pmmp\webrtc\WebRtcException;
use pocketmine\nethernet\protocol\Reliability;
use pocketmine\nethernet\transport\MessageChannel;
use pocketmine\nethernet\transport\TransportException;

final class WebRtcMessageChannel implements MessageChannel{

	public function __construct(
		private readonly DataChannel $channel
	){}

	public function getLabel() : string{
		return $this->channel->getLabel();
	}

	public function getReliability() : ?Reliability{
		$channel = $this->channel;
		if($channel->getProtocol() !== "" || $channel->getMaxPacketLifeTime() !== null){
			return null;
		}

		$reliability = Reliability::fromChannelLabel($channel->getLabel());

		return match(true){
			$reliability === Reliability::RELIABLE && !$channel->isUnordered() && $channel->getMaxRetransmits() === null,
			$reliability === Reliability::UNRELIABLE && $channel->isUnordered() && $channel->getMaxRetransmits() === 0 => $reliability,
			default => null
		};
	}

	public function isOpen() : bool{
		return $this->channel->isOpen();
	}

	public function isClosed() : bool{
		return $this->channel->isClosed();
	}

	public function getMaxMessageSize() : int{
		return $this->channel->getMaxMessageSize();
	}

	public function send(string $message) : void{
		try{
			$this->channel->send($message);
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function receive() : ?string{
		try{
			return $this->channel->receive();
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function getBufferedAmount() : int{
		return $this->channel->getBufferedAmount();
	}

	public function getAvailableAmount() : int{
		return $this->channel->getAvailableAmount();
	}

	public function getQueuedMessageCount() : int{
		return $this->channel->getQueuedMessageCount();
	}

	public function close() : void{
		try{
			$this->channel->close();
		}catch(WebRtcException){
			//already gone, which is what was asked for
		}
	}
}
