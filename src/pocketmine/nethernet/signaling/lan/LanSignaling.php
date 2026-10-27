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

namespace pocketmine\nethernet\signaling\lan;

use pocketmine\nethernet\info\ServerInfoCodec;
use pocketmine\nethernet\info\ServerInfoProvider;
use pocketmine\nethernet\negotiation\CandidateMode;
use pocketmine\nethernet\negotiation\NegotiationException;
use pocketmine\nethernet\negotiation\Negotiator;
use pocketmine\nethernet\protocol\ErrorCode;
use pocketmine\nethernet\signaling\lan\packet\DiscoveryException;
use pocketmine\nethernet\signaling\lan\packet\MessagePacket;
use pocketmine\nethernet\signaling\lan\packet\Packet;
use pocketmine\nethernet\signaling\lan\packet\PacketSerializer;
use pocketmine\nethernet\signaling\lan\packet\RequestPacket;
use pocketmine\nethernet\signaling\lan\packet\ResponsePacket;
use pocketmine\nethernet\signaling\Signal;
use pocketmine\nethernet\signaling\Signaling;
use pocketmine\nethernet\signaling\SignalingContext;
use pocketmine\nethernet\signaling\SignalingException;
use pocketmine\nethernet\signaling\SignalingState;
use pocketmine\nethernet\signaling\SignalType;
use pocketmine\nethernet\utils\AddressBlockList;
use pocketmine\nethernet\utils\DatagramSocket;
use pocketmine\nethernet\utils\InternetAddress;
use pocketmine\nethernet\utils\SocketException;
use function sprintf;

/**
 * Answers LAN discovery requests and exchanges offers, answers and trickled candidates with peers over UDP.
 */
final class LanSignaling implements Signaling{

	public const DEFAULT_PORT = 7551;

	public const MAX_DATAGRAMS_PER_TICK = 256;

	public const DEFAULT_MAX_CONNECTIONS = 64;
	/** Answered per address; null in the constructor lifts the limit. */
	public const DEFAULT_MAX_DISCOVERY_REQUESTS_PER_SECOND = 64;

	private const PING_DATA = "Ping";

	private ?DatagramSocket $socket = null;

	/**
	 * Kept until the peer connection closes, so that a signal for an established connection still finds it.
	 *
	 * @var LanConnection[]
	 * @phpstan-var array<string, LanConnection>
	 */
	private array $connections = [];

	/** @phpstan-var array<string, int> keyed by address, for the current second */
	private array $discoveryRequests = [];
	private float $discoveryRequestsExpiry = 0.0;

	private SignalingState $state = SignalingState::CREATED;

	/* Collaborators handed over by the server in start(); nothing reads them before that. */
	private Negotiator $negotiator;
	private AddressBlockList $blockList;
	private \Logger $logger;

	/**
	 * @param int $networkId 64-bit unsigned integer identifying this host on the network. PHP has no such type, so
	 *                       IDs above the signed range are passed as the negative integer with the same bits.
	 */
	public function __construct(
		private readonly ServerInfoProvider $infoProvider,
		private readonly int $networkId,
		private readonly InternetAddress $bindAddress = new InternetAddress("0.0.0.0", self::DEFAULT_PORT, 4),
		private readonly int $maxConnections = self::DEFAULT_MAX_CONNECTIONS,
		private readonly ?int $maxDiscoveryRequestsPerSecond = self::DEFAULT_MAX_DISCOVERY_REQUESTS_PER_SECOND
	){
		if($networkId === 0){
			throw new \InvalidArgumentException("Network id must not be zero");
		}
		if($maxConnections < 1){
			throw new \InvalidArgumentException("Maximum connections must be positive, got $maxConnections");
		}
		if($maxDiscoveryRequestsPerSecond !== null && $maxDiscoveryRequestsPerSecond < 1){
			throw new \InvalidArgumentException("Maximum discovery requests per second must be positive or null, got $maxDiscoveryRequestsPerSecond");
		}
	}

	public function getNetworkId() : int{ return $this->networkId; }

	/**
	 * Returns the address the transport is listening on, with the port the system chose if none was configured.
	 *
	 * @throws \LogicException if the transport is not started
	 */
	public function getBoundAddress() : InternetAddress{
		return $this->socket?->getBindAddress() ?? throw new \LogicException("LAN signaling is not listening");
	}

	public function describe() : string{
		return "LAN signaling on " . ($this->socket?->getBindAddress() ?? $this->bindAddress);
	}

	public function start(SignalingContext $context) : void{
		$this->state->checkCanStart();

		try{
			$this->socket = new DatagramSocket($this->bindAddress, broadcast: true);
		}catch(SocketException $e){
			throw new SignalingException($e->getMessage(), $e->getCode(), $e);
		}

		$this->negotiator = $context->negotiator;
		$this->blockList = $context->blockList;
		$this->logger = new \PrefixedLogger($context->logger, "LAN signaling");
		$this->state = SignalingState::STARTED;

		$this->logger->debug("Listening on " . $this->socket->getBindAddress() . " as network id " . self::formatId($this->networkId));
	}

	public function tick(float $now) : void{
		$socket = $this->socket;
		if($this->state !== SignalingState::STARTED || $socket === null){
			return;
		}

		if($now >= $this->discoveryRequestsExpiry){
			$this->discoveryRequests = [];
			$this->discoveryRequestsExpiry = $now + 1.0;
		}

		$this->receiveAll($socket);
		$this->processConnections();
	}

	public function shutdown() : void{
		if($this->state === SignalingState::SHUT_DOWN){
			return;
		}
		$this->state = SignalingState::SHUT_DOWN;

		foreach($this->connections as $connection){
			$connection->negotiation->fail("LAN signaling is shutting down", ErrorCode::NO_SIGNALING_CHANNEL);
		}
		$this->connections = [];

		$this->socket?->close();
		$this->socket = null;
	}

	private function receiveAll(DatagramSocket $socket) : void{
		for($i = 0; $i < self::MAX_DATAGRAMS_PER_TICK; ++$i){
			try{
				$received = $socket->receive();
			}catch(SocketException $e){
				$this->logger->warning($e->getMessage());

				return;
			}
			if($received === null){
				return;
			}

			[$datagram, $address] = $received;
			if($this->blockList->isBlocked($address->ip)){
				continue;
			}

			try{
				$this->handleDatagram($datagram, $address);
			}catch(SignalingException $e){
				$this->logger->debug("Ignoring a datagram from $address: " . $e->getMessage());
			}
		}
	}

	/**
	 * @throws SignalingException
	 */
	private function handleDatagram(string $datagram, InternetAddress $address) : void{
		$packet = PacketSerializer::decode($datagram);

		/* Broadcasts come straight back to the socket that sent them. */
		if($packet->senderId === $this->networkId){
			return;
		}

		if($packet instanceof RequestPacket){
			$requests = $this->discoveryRequests[$address->ip] = ($this->discoveryRequests[$address->ip] ?? 0) + 1;
			if($this->maxDiscoveryRequestsPerSecond !== null && $requests > $this->maxDiscoveryRequestsPerSecond){
				return;
			}

			$info = $this->infoProvider->getServerInfo();
			if($info !== null){
				$this->send(new ResponsePacket($this->networkId, ServerInfoCodec::encode($info)), $address);
			}

			return;
		}
		if($packet instanceof MessagePacket){
			$this->handleMessage($packet, $address);
		}
	}

	/**
	 * @throws SignalingException
	 */
	private function handleMessage(MessagePacket $packet, InternetAddress $address) : void{
		if($packet->recipientId !== $this->networkId){
			return;
		}
		if($packet->data === "" || $packet->data === self::PING_DATA){
			return;
		}

		$signal = Signal::parse($packet->data);
		$senderId = $packet->senderId;
		$key = $senderId . "/" . $signal->connectionId;

		match($signal->type){
			SignalType::CONNECT_REQUEST => $this->handleOffer($signal, $senderId, $key, $address),
			SignalType::CANDIDATE_ADD => $this->handleCandidate($signal, $key),
			SignalType::CONNECT_ERROR => $this->handlePeerError($signal, $key),
			SignalType::CONNECT_RESPONSE => null
		};
	}

	private function handleOffer(Signal $signal, int $senderId, string $key, InternetAddress $address) : void{
		if(isset($this->connections[$key])){
			return;
		}
		if($this->countNegotiating() >= $this->maxConnections){
			/* Nothing is held for this offer, so nothing is owed to whoever sent it. */
			$this->logger->debug("Ignored an offer from " . self::formatId($senderId) . ": connection limit of $this->maxConnections reached");

			return;
		}

		try{
			$negotiation = $this->negotiator->beginNegotiation($signal->data, self::formatId($senderId), CandidateMode::TRICKLE);
		}catch(NegotiationException $e){
			$this->logger->debug("Refused an offer from " . self::formatId($senderId) . ": " . $e->getMessage());
			$this->sendSignal($senderId, Signal::error($signal->connectionId, $e->getErrorCode()), $address);

			return;
		}

		$this->connections[$key] = new LanConnection($negotiation, $senderId, $address, $signal->connectionId);
	}

	private function handleCandidate(Signal $signal, string $key) : void{
		$connection = $this->connections[$key] ?? null;
		if($connection === null){
			return;
		}

		try{
			$connection->negotiation->addRemoteCandidate($signal->data);
		}catch(NegotiationException $e){
			$this->logger->debug("Peer sent an unusable ICE candidate: " . $e->getMessage());
		}
	}

	private function handlePeerError(Signal $signal, string $key) : void{
		$connection = $this->connections[$key] ?? null;
		//signals are not authenticated, so one must not be able to end an established session
		if($connection === null || $connection->negotiation->isFinished()){
			return;
		}

		/* The peer already knows the connection failed, so it is dropped here rather than reported back. */
		unset($this->connections[$key]);
		$connection->negotiation->fail("Peer reported error code " . $signal->data, ErrorCode::GENERIC_FAILURE);
	}

	private function countNegotiating() : int{
		$count = 0;
		foreach($this->connections as $connection){
			if(!$connection->negotiation->isFinished()){
				$count++;
			}
		}

		return $count;
	}

	private function processConnections() : void{
		foreach($this->connections as $key => $connection){
			foreach($connection->takeSignals() as $signal){
				$this->sendSignal($connection->peerId, $signal, $connection->address);
			}
			if($connection->negotiation->isClosed()){
				unset($this->connections[$key]);
			}
		}
	}

	private function sendSignal(int $peerId, Signal $signal, InternetAddress $address) : void{
		$this->send(new MessagePacket($this->networkId, $peerId, $signal->toString()), $address);
	}

	private function send(Packet $packet, InternetAddress $address) : void{
		try{
			$this->socket?->send(PacketSerializer::encode($packet), $address);
		}catch(SocketException|DiscoveryException $e){
			$this->logger->debug("Could not send to $address: " . $e->getMessage());
		}
	}

	private static function formatId(int $networkId) : string{
		return sprintf("%u", $networkId);
	}
}
