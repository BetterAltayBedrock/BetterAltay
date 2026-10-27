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

namespace pocketmine\nethernet\server;

use pocketmine\nethernet\negotiation\EstablishedPeer;
use pocketmine\nethernet\protocol\DisconnectReason;
use pocketmine\nethernet\protocol\framing\Segmenter;
use pocketmine\nethernet\session\Session;
use pocketmine\nethernet\session\SessionException;
use pocketmine\nethernet\transport\ChannelPair;
use pocketmine\nethernet\transport\ConnectionBudgetConfiguration;
use function array_values;
use function count;
use function min;

/**
 * @internal
 */
final class SessionManager{

	/** Packets read from each session per tick, so that one busy client cannot hold up everyone else's. */
	public const PACKETS_PER_TICK_LIMIT = 64;

	/** Seconds between bandwidth reports and ping checks. */
	public const STATS_INTERVAL = 1.0;

	/**
	 * @var Session[]
	 * @phpstan-var array<int, Session>
	 */
	private array $sessions = [];

	private int $nextSessionId = 0;

	private readonly Segmenter $segmenter;

	private int $bytesSentDiff = 0;
	private int $bytesReceivedDiff = 0;
	private ?float $nextStatsTime = null;

	public function __construct(
		private readonly ServerEventListener $listener,
		private readonly ConnectionBudgetConfiguration $budget,
		private readonly \Logger $logger
	){
		$this->segmenter = new Segmenter($budget->maxMessageSize - Segmenter::HEADER_SIZE);
	}

	public function open(EstablishedPeer $peer) : Session{
		$session = new Session(
			$this->nextSessionId++,
			$peer->transport,
			$peer->channels,
			$peer->address,
			$peer->clientAddress,
			$this->segmenterFor($peer->channels),
			$this->budget
		);
		$id = $session->getId();

		$this->logger->debug("Session $id opened for network id $peer->networkId from $peer->address" . ($peer->clientAddress !== null ? " (client $peer->clientAddress)" : ""));

		/* Registered first, so that the listener can already close or block the session it is being told about. */
		$this->sessions[$id] = $session;

		try{
			$this->listener->onSessionOpen($id, $peer->networkId, $peer->address, $peer->clientAddress, $peer->identity);
		}catch(\Throwable $e){
			/* The listener never took the session on, so there is nothing to tell it about the session ending. */
			unset($this->sessions[$id]);
			$session->forciblyDisconnect(DisconnectReason::REJECTED_BY_HOST);

			throw $e;
		}

		return $session;
	}

	private function segmenterFor(ChannelPair $channels) : Segmenter{
		$segmentPayloadSize = min($channels->reliable->getMaxMessageSize(), $channels->unreliable->getMaxMessageSize()) - Segmenter::HEADER_SIZE;

		return $segmentPayloadSize >= 1 && $segmentPayloadSize < $this->segmenter->getMaxSegmentPayloadSize() ? new Segmenter($segmentPayloadSize) : $this->segmenter;
	}

	public function tick(float $now) : void{
		foreach($this->sessions as $id => $session){
			/* Before the update: a disconnect that finishes flushing closes the session, and its last receipts are owed first. */
			foreach($session->takeAcknowledgedReceipts() as $receiptId){
				$this->listener->onPacketAck($id, $receiptId);
			}

			$session->update($now);

			if($session->isFullyDisconnected()){
				$this->removeSession($session, DisconnectReason::SERVER_DISCONNECT);
				continue;
			}

			if($session->isConnected()){
				$this->processIncoming($session);
			}
		}

		$this->nextStatsTime ??= $now + self::STATS_INTERVAL;
		if($now >= $this->nextStatsTime){
			$this->nextStatsTime = $now + self::STATS_INTERVAL;
			$this->reportStats();
		}
	}

	private function processIncoming(Session $session) : void{
		$id = $session->getId();
		for($i = 0; $i < self::PACKETS_PER_TICK_LIMIT; ++$i){
			if(!$this->listener->canAcceptPackets()){
				return;
			}

			try{
				$message = $session->receive();
			}catch(SessionException $e){
				$this->logger->debug("Session $id failed while reading: " . $e->getMessage());
				$this->removeSession($session, DisconnectReason::BAD_DATA);

				return;
			}

			if($message === null){
				return;
			}

			$this->listener->onPacketReceive($id, $message->payload, $message->reliability);

			/* The listener may have disconnected the session while handling the packet. */
			if($session->isFullyDisconnected()){
				$this->removeSession($session, DisconnectReason::SERVER_DISCONNECT);

				return;
			}
			if(!$session->isConnected()){
				return;
			}
		}
	}

	private function collectBandwidth(Session $session) : void{
		[$sent, $received] = $session->takeBandwidth();
		$this->bytesSentDiff += $sent;
		$this->bytesReceivedDiff += $received;
	}

	private function reportStats() : void{
		foreach($this->sessions as $session){
			$this->collectBandwidth($session);
		}

		if($this->bytesSentDiff > 0 || $this->bytesReceivedDiff > 0){
			$this->listener->onBandwidthStatsUpdate($this->bytesSentDiff, $this->bytesReceivedDiff);
			$this->bytesSentDiff = 0;
			$this->bytesReceivedDiff = 0;
		}

		foreach($this->sessions as $id => $session){
			$ping = $session->takePingChange();
			if($ping !== null){
				$this->listener->onPingMeasure($id, $ping);
			}
		}
	}

	private function removeSession(Session $session, DisconnectReason $reason) : void{
		$id = $session->getId();
		if(!isset($this->sessions[$id])){
			return;
		}

		$session->forciblyDisconnect($reason);
		$reason = $session->getDisconnectReason() ?? $reason;

		/* The transport keeps its final byte counts, so what the session exchanged in its last moments is still counted. */
		$this->collectBandwidth($session);
		unset($this->sessions[$id]);

		$this->logger->debug("Session $id closed: " . $reason->getMessage());
		$this->listener->onSessionClose($id, $reason);
	}

	public function getSession(int $id) : ?Session{
		return $this->sessions[$id] ?? null;
	}

	/**
	 * @return Session[]
	 * @phpstan-return list<Session>
	 */
	public function getSessions() : array{
		return array_values($this->sessions);
	}

	public function count() : int{
		return count($this->sessions);
	}

	public function shutdown(DisconnectReason $reason = DisconnectReason::SERVER_SHUTDOWN) : void{
		foreach($this->sessions as $session){
			$this->removeSession($session, $reason);
		}
	}
}
