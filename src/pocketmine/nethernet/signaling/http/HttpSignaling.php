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

namespace pocketmine\nethernet\signaling\http;

use pocketmine\nethernet\info\ServerInfoProvider;
use pocketmine\nethernet\signaling\Signaling;
use pocketmine\nethernet\signaling\SignalingContext;
use pocketmine\nethernet\signaling\SignalingException;
use pocketmine\nethernet\signaling\SignalingState;
use pocketmine\nethernet\utils\AddressBlockList;
use pocketmine\nethernet\utils\InternetAddress;
use pocketmine\nethernet\utils\SocketException;
use pocketmine\nethernet\utils\TcpServerSocket;
use function count;
use function socket_close;
use function socket_export_stream;
use function socket_set_nonblock;
use function stream_set_blocking;

/**
 * Takes offers over HTTP or HTTPS and answers each with the SDP answer, as NetherNet clients joining by address expect.
 */
final class HttpSignaling implements Signaling{

	public const MAX_NETWORK_ID_LENGTH = 256;
	public const DEFAULT_MAX_CONNECTIONS = 256;

	private ?TcpServerSocket $socket = null;

	/**
	 * @var HttpConnection[]
	 * @phpstan-var array<int, HttpConnection>
	 */
	private array $connections = [];

	private int $nextConnectionId = 0;
	private SignalingState $state = SignalingState::CREATED;

	/* Collaborators handed over by the server in start(); nothing reads them before that. */
	private JoinHandler $handler;
	private AddressBlockList $blockList;
	private \Logger $logger;

	/**
	 * @param InternetAddress         $bindAddress    Address to listen on; port 0 lets the system choose one.
	 * @param TlsConfiguration|null   $tls            Certificate to serve HTTPS with, or null to serve plain HTTP.
	 * @param ServerInfoProvider|null $infoProvider   Supplies what capability probes (`GET /v1/join`) are answered with.
	 * @param ReverseProxy|null       $reverseProxy   Proxies trusted to name the client's address in a header.
	 * @param int                     $maxConnections Connections served at once.
	 */
	public function __construct(
		private readonly InternetAddress $bindAddress,
		private readonly ?TlsConfiguration $tls = null,
		private readonly ?ServerInfoProvider $infoProvider = null,
		private readonly ?ReverseProxy $reverseProxy = null,
		private readonly int $maxConnections = self::DEFAULT_MAX_CONNECTIONS
	){
		if($maxConnections < 1){
			throw new \InvalidArgumentException("Maximum connections must be positive, got $maxConnections");
		}
	}

	/**
	 * Returns the address the transport is listening on, with the port the system chose if none was configured.
	 *
	 * @throws \LogicException if the transport is not started
	 */
	public function getBoundAddress() : InternetAddress{
		return $this->socket?->getBindAddress() ?? throw new \LogicException("HTTP signaling is not listening");
	}

	public function describe() : string{
		return "HTTP signaling on " . ($this->socket?->getBindAddress() ?? $this->bindAddress);
	}

	public function start(SignalingContext $context) : void{
		$this->state->checkCanStart();

		$this->tls?->checkReadable();
		try{
			$this->socket = new TcpServerSocket($this->bindAddress);
		}catch(SocketException $e){
			throw new SignalingException($e->getMessage(), $e->getCode(), $e);
		}

		$this->logger = new \PrefixedLogger($context->logger, "HTTP signaling");
		$this->blockList = $context->blockList;
		$this->handler = new JoinHandler($context->negotiator, $context->blockList, $this->infoProvider, $this->reverseProxy, $this->logger);
		$this->state = SignalingState::STARTED;
	}

	public function tick(float $now) : void{
		$socket = $this->socket;
		if($this->state !== SignalingState::STARTED || $socket === null){
			return;
		}

		$this->acceptAll($socket, $now);

		foreach($this->connections as $id => $connection){
			$connection->advance($now, $this->handler);
			if($connection->isDone()){
				$connection->close();
				unset($this->connections[$id]);
			}
		}
	}

	private function acceptAll(TcpServerSocket $socket, float $now) : void{
		while(true){
			try{
				$accepted = $socket->accept();
			}catch(SocketException $e){
				$this->logger->warning($e->getMessage());

				return;
			}
			if($accepted === null){
				return;
			}

			[$client, $peerAddress] = $accepted;
			if($peerAddress !== null && $this->blockList->isBlocked($peerAddress->ip)){
				$this->logger->debug("Dropped a signaling connection from blocked address $peerAddress->ip");
				socket_close($client);
				continue;
			}
			if(count($this->connections) >= $this->maxConnections && !$this->evictStalest()){
				$this->logger->debug("Refused signaling connection: connection limit of $this->maxConnections reached");
				socket_close($client);
				continue;
			}

			/* TLS is only available on streams, so the accepted socket is driven as one from here on. */
			socket_set_nonblock($client);
			$stream = socket_export_stream($client);
			if($stream === false){
				$this->logger->debug("Could not read the accepted socket as a stream; dropping " . ($peerAddress?->toString() ?? "unknown"));
				socket_close($client);
				continue;
			}
			stream_set_blocking($stream, false);

			$connection = new HttpConnection($stream, $client, $peerAddress, $this->tls, $this->logger, $now);
			$this->logger->debug("Signaling connection opened from " . $connection->getPeerName());
			$this->connections[$this->nextConnectionId++] = $connection;
		}
	}

	/**
	 * Closes the connection that has been waiting longest on its client, to make room for a new one.
	 *
	 * @return bool false if every connection already has its request and is waiting on this server
	 */
	private function evictStalest() : bool{
		$stalestId = null;
		foreach($this->connections as $id => $connection){
			if($connection->getState()->isAwaitingRequest() && ($stalestId === null || $connection->getDeadline() < $this->connections[$stalestId]->getDeadline())){
				$stalestId = $id;
			}
		}
		if($stalestId === null){
			return false;
		}

		$this->logger->debug("Evicting stalest signaling connection (" . $this->connections[$stalestId]->getPeerName() . ") to accommodate new connection");
		$this->connections[$stalestId]->close();
		unset($this->connections[$stalestId]);

		return true;
	}

	public function shutdown() : void{
		if($this->state === SignalingState::SHUT_DOWN){
			return;
		}
		$this->state = SignalingState::SHUT_DOWN;

		foreach($this->connections as $connection){
			$connection->close();
		}
		$this->connections = [];

		$this->socket?->close();
		$this->socket = null;
	}
}
