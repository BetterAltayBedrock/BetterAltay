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

use pmmp\webrtc\ConnectionState;
use pmmp\webrtc\GatheringState;
use pmmp\webrtc\IceCandidate;
use pmmp\webrtc\PeerConnection;
use pmmp\webrtc\WebRtcException;
use pocketmine\nethernet\transport\PeerTransport;
use pocketmine\nethernet\transport\TransportException;
use pocketmine\nethernet\transport\TransportState;
use pocketmine\nethernet\utils\InternetAddress;

/**
 * A closed peer connection throws on every call, so the facts that outlive it (final byte counts, the peer's
 * address, why it ended) are captured as it closes.
 */
final class WebRtcPeerTransport implements PeerTransport{

	private bool $closed = false;

	private bool $lostConnectivity = false;
	private ?InternetAddress $remoteAddress = null;
	private int $bytesSent = 0;
	private int $bytesReceived = 0;

	public function __construct(
		private readonly PeerConnection $peerConnection
	){}

	public function setRemoteOffer(string $sdp) : void{
		try{
			$this->peerConnection->setRemoteOffer($sdp);
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function getLocalDescription() : ?string{
		try{
			return $this->peerConnection->getLocalDescription();
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function isGatheringComplete() : bool{
		try{
			return $this->peerConnection->getGatheringState() === GatheringState::COMPLETE;
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function addRemoteCandidate(string $candidate) : void{
		try{
			$this->peerConnection->addRemoteCandidate(IceCandidate::create($candidate));
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function pollLocalCandidates() : array{
		try{
			$candidates = [];
			foreach($this->peerConnection->pollLocalCandidates() as $candidate){
				$candidates[] = $candidate->getCandidate();
			}

			return $candidates;
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function pollChannels() : array{
		try{
			$channels = [];
			foreach($this->peerConnection->pollDataChannels() as $channel){
				$channels[] = new WebRtcMessageChannel($channel);
			}

			return $channels;
		}catch(WebRtcException $e){
			throw new TransportException($e->getMessage(), 0, $e);
		}
	}

	public function getState() : TransportState{
		if($this->closed){
			return TransportState::CLOSED;
		}

		try{
			return match($this->peerConnection->getState()){
				ConnectionState::NOT_STARTED, ConnectionState::CONNECTING => TransportState::CONNECTING,
				ConnectionState::CONNECTED => TransportState::CONNECTED,
				ConnectionState::DISCONNECTED => TransportState::DISCONNECTED,
				ConnectionState::FAILED => TransportState::FAILED,
				ConnectionState::CLOSED => TransportState::CLOSED
			};
		}catch(WebRtcException){
			return TransportState::CLOSED;
		}
	}

	public function hasLostConnectivity() : bool{
		if(!$this->closed){
			try{
				return $this->peerConnection->getFailureState() === ConnectionState::FAILED;
			}catch(WebRtcException){
				return false;
			}
		}

		return $this->lostConnectivity;
	}

	public function getRemoteAddress() : ?InternetAddress{
		if(!$this->closed){
			try{
				$address = $this->peerConnection->getRemoteAddress();
			}catch(WebRtcException){
				$address = null;
			}
			if($address !== null){
				$this->remoteAddress = InternetAddress::parse($address) ?? $this->remoteAddress;
			}
		}

		return $this->remoteAddress;
	}

	public function getRoundTripTime() : ?int{
		if($this->closed){
			return null;
		}

		try{
			return $this->peerConnection->getRoundTripTime();
		}catch(WebRtcException){
			return null;
		}
	}

	public function getBytesSent() : int{
		if(!$this->closed){
			try{
				$this->bytesSent = $this->peerConnection->getBytesSent();
			}catch(WebRtcException){
				//keep the last count
			}
		}

		return $this->bytesSent;
	}

	public function getBytesReceived() : int{
		if(!$this->closed){
			try{
				$this->bytesReceived = $this->peerConnection->getBytesReceived();
			}catch(WebRtcException){
				//keep the last count
			}
		}

		return $this->bytesReceived;
	}

	public function close() : void{
		if($this->closed){
			return;
		}

		$this->lostConnectivity = $this->hasLostConnectivity();
		$this->getRemoteAddress();
		$this->getBytesSent();
		$this->getBytesReceived();
		$this->closed = true;

		try{
			$this->peerConnection->close();
		}catch(WebRtcException){
			//already gone, which is what was asked for
		}
	}
}
