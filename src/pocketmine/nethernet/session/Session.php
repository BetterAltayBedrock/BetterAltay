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

namespace pocketmine\nethernet\session;

use pocketmine\nethernet\protocol\DisconnectReason;
use pocketmine\nethernet\protocol\framing\Assembler;
use pocketmine\nethernet\protocol\framing\FramingException;
use pocketmine\nethernet\protocol\framing\Segmenter;
use pocketmine\nethernet\protocol\Reliability;
use pocketmine\nethernet\transport\ChannelPair;
use pocketmine\nethernet\transport\ConnectionBudgetConfiguration;
use pocketmine\nethernet\transport\PeerTransport;
use pocketmine\nethernet\transport\TransportException;
use pocketmine\nethernet\transport\TransportState;
use pocketmine\nethernet\utils\InternetAddress;
use function count;
use function strlen;

/**
 * @internal
 */
final class Session{

	/** Timeout in seconds to flush queued outgoing data before disconnecting. */
	public const DISCONNECT_FLUSH_TIMEOUT = 2.0;

	/** @phpstan-var array<string, Assembler> keyed by Reliability case name */
	private readonly array $assemblers;

	private SessionState $state = SessionState::CONNECTED;
	/** Why the session is disconnecting or has disconnected. */
	private ?DisconnectReason $disconnectReason = null;

	private int $reportedBytesSent = 0;
	private int $reportedBytesReceived = 0;
	private ?int $reportedPing = null;

	/** @phpstan-var array<string, int> bytes handed to each channel so far, keyed by Reliability case name */
	private array $queuedBytes = [];

	/**
	 * Receipts not yet acknowledged, oldest first, each with the queued byte count its packet ended at.
	 * @phpstan-var array<string, \SplQueue<array{int, int}>>
	 */
	private array $pendingReceipts = [];

	private int $pendingReceiptCount = 0;

	/** Measured from the first update that finds the disconnect pending. */
	private ?float $disconnectDeadline = null;

	public function __construct(
		private readonly int $id,
		private readonly PeerTransport $transport,
		private readonly ChannelPair $channels,
		private readonly InternetAddress $address,
		private readonly ?string $clientAddress,
		private readonly Segmenter $segmenter,
		private readonly ConnectionBudgetConfiguration $budget
	){
		$assemblers = [];
		foreach(Reliability::cases() as $reliability){
			$assemblers[$reliability->name] = new Assembler($reliability->isFragmentationSupported(), $budget->maxPayloadSize);
			$this->queuedBytes[$reliability->name] = 0;
			$this->pendingReceipts[$reliability->name] = new \SplQueue();
		}
		$this->assemblers = $assemblers;
	}

	public function getId() : int{ return $this->id; }

	public function getRemoteAddress() : InternetAddress{ return $this->address; }

	public function getClientAddress() : ?string{ return $this->clientAddress; }

	public function isConnected() : bool{ return $this->state === SessionState::CONNECTED; }

	public function isFullyDisconnected() : bool{ return $this->state === SessionState::DISCONNECTED; }

	public function getDisconnectReason() : ?DisconnectReason{ return $this->disconnectReason; }

	/**
	 * Queues a packet for the peer. Packets sent after a disconnect was initiated are dropped.
	 * @throws \InvalidArgumentException
	 * @throws SessionException
	 */
	public function send(string $payload, Reliability $reliability = Reliability::RELIABLE, ?int $receiptId = null) : void{
		if($this->state === SessionState::DISCONNECT_PENDING){
			return;
		}
		$this->requireOpen();

		try{
			$segments = $this->segmenter->segment($payload);
		}catch(FramingException $e){
			throw new \InvalidArgumentException($e->getMessage(), 0, $e);
		}
		if(count($segments) > 1 && !$reliability->isFragmentationSupported()){
			throw new \InvalidArgumentException("Payload requires " . count($segments) . " segments, but " . $reliability->getChannelLabel() . " does not support fragmentation");
		}

		try{
			$channel = $this->channels->get($reliability);
			foreach($segments as $segment){
				$channel->send($segment);
				$this->queuedBytes[$reliability->name] += strlen($segment);
			}
		}catch(TransportException $e){
			$this->forciblyDisconnect(DisconnectReason::SEND_FAILED);

			throw new SessionException("Failed to send on channel " . $reliability->getChannelLabel() . ": " . $e->getMessage(), 0, $e);
		}

		if($receiptId !== null){
			$this->pendingReceipts[$reliability->name]->enqueue([$receiptId, $this->queuedBytes[$reliability->name]]);
			++$this->pendingReceiptCount;
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return list<int>
	 */
	public function takeAcknowledgedReceipts() : array{
		if($this->state === SessionState::DISCONNECTED || $this->pendingReceiptCount === 0){
			return [];
		}

		$acknowledged = [];
		foreach(Reliability::cases() as $reliability){
			$pending = $this->pendingReceipts[$reliability->name];
			if($pending->isEmpty()){
				continue;
			}

			$delivered = $this->queuedBytes[$reliability->name] - $this->channels->get($reliability)->getBufferedAmount();
			while(!$pending->isEmpty()){
				[$receiptId, $watermark] = $pending->bottom();
				if($watermark > $delivered){
					break;
				}
				$pending->dequeue();
				--$this->pendingReceiptCount;
				$acknowledged[] = $receiptId;
			}
		}

		return $acknowledged;
	}

	/**
	 * @throws SessionException
	 */
	public function receive() : ?ReceivedMessage{
		$this->requireOpen();

		foreach(Reliability::cases() as $reliability){
			$channel = $this->channels->get($reliability);
			$assembler = $this->assemblers[$reliability->name];

			try{
				while(($segment = $channel->receive()) !== null){
					$payload = $assembler->accept($segment);
					if($payload !== null){
						return new ReceivedMessage($reliability, $payload);
					}
				}
			}catch(FramingException|TransportException $e){
				$this->forciblyDisconnect(DisconnectReason::BAD_DATA);

				throw new SessionException("Bad data on channel " . $reliability->getChannelLabel() . ": " . $e->getMessage(), 0, $e);
			}
		}

		return null;
	}

	/**
	 * @phpstan-return array{int, int} bytes sent and received since the last call
	 */
	public function takeBandwidth() : array{
		$sent = $this->transport->getBytesSent();
		$received = $this->transport->getBytesReceived();
		$diff = [$sent - $this->reportedBytesSent, $received - $this->reportedBytesReceived];
		$this->reportedBytesSent = $sent;
		$this->reportedBytesReceived = $received;

		return $diff;
	}

	/**
	 * Returns the round trip time in milliseconds if it differs from what the last call returned.
	 */
	public function takePingChange() : ?int{
		$ping = $this->transport->getRoundTripTime();
		if($ping === null || $ping === $this->reportedPing){
			return null;
		}

		return $this->reportedPing = $ping;
	}

	public function update(float $now) : void{
		if($this->state === SessionState::DISCONNECTED){
			return;
		}

		$transportState = $this->transport->getState();
		if($transportState === TransportState::FAILED){
			$this->forciblyDisconnect(DisconnectReason::CONNECTION_FAILED);

			return;
		}

		if($this->state === SessionState::DISCONNECT_PENDING){
			$this->disconnectDeadline ??= $now + self::DISCONNECT_FLUSH_TIMEOUT;
			if($this->bufferedBytes() === 0 || $transportState !== TransportState::CONNECTED || $now >= $this->disconnectDeadline){
				$this->forciblyDisconnect($this->disconnectReason ?? DisconnectReason::SERVER_DISCONNECT);
			}

			return;
		}

		/* Check queue limits before channel closures so flooding is reported accurately. */
		$queuedBytes = 0;
		$queuedMessages = 0;
		foreach($this->channels->all() as $channel){
			$queuedBytes += $channel->getAvailableAmount();
			$queuedMessages += $channel->getQueuedMessageCount();
		}
		if($queuedBytes > $this->budget->maxReceiveQueueSize){
			$this->forciblyDisconnect(DisconnectReason::RECEIVE_QUEUE_TOO_MANY_BYTES);

			return;
		}
		if($queuedMessages > $this->budget->maxReceiveQueueMessages){
			$this->forciblyDisconnect(DisconnectReason::RECEIVE_QUEUE_TOO_MANY_MESSAGES);

			return;
		}
		if($this->bufferedBytes() > $this->budget->maxSendQueueSize){
			$this->forciblyDisconnect(DisconnectReason::SEND_QUEUE_TOO_MANY_BYTES);

			return;
		}

		if($transportState === TransportState::CLOSED || $this->hasClosedChannel()){
			$this->forciblyDisconnect($this->getTransportDisconnectReason());
		}
	}

	private function hasClosedChannel() : bool{
		foreach($this->channels->all() as $channel){
			if($channel->isClosed()){
				return true;
			}
		}

		return false;
	}

	private function getTransportDisconnectReason() : DisconnectReason{
		return $this->transport->hasLostConnectivity() ? DisconnectReason::CONNECTION_FAILED : DisconnectReason::PEER_DISCONNECT;
	}

	public function initiateDisconnect(DisconnectReason $reason) : void{
		if($this->state !== SessionState::CONNECTED){
			return;
		}

		$this->state = SessionState::DISCONNECT_PENDING;
		$this->disconnectReason = $reason;
	}

	private function bufferedBytes() : int{
		$buffered = 0;
		foreach($this->channels->all() as $channel){
			$buffered += $channel->getBufferedAmount();
		}

		return $buffered;
	}

	/** Closes the transport immediately, discarding anything still queued. The first reason given is the one kept. */
	public function forciblyDisconnect(DisconnectReason $reason) : void{
		if($this->state === SessionState::DISCONNECTED){
			return;
		}
		$this->state = SessionState::DISCONNECTED;
		$this->disconnectReason = $reason;

		foreach($this->channels->all() as $channel){
			$channel->close();
		}
		$this->transport->close();
	}

	/**
	 * @throws SessionException
	 */
	private function requireOpen() : void{
		if($this->state === SessionState::DISCONNECTED){
			throw new SessionException("Session is closed" . ($this->disconnectReason !== null ? ": " . $this->disconnectReason->getMessage() : ""));
		}
		if($this->hasClosedChannel()){
			$this->forciblyDisconnect($this->getTransportDisconnectReason());

			throw new SessionException("Data channel of the session is closed");
		}
	}
}
