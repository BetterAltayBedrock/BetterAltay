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

use pocketmine\nethernet\identity\PeerIdentity;
use pocketmine\nethernet\protocol\DisconnectReason;
use pocketmine\nethernet\protocol\Reliability;
use pocketmine\nethernet\utils\InternetAddress;

interface ServerEventListener{

	/**
	 * The session can be turned away from here with {@link ServerInterface::closeSession()}; it is then reported to
	 * onSessionClose() like any other, without any of its packets having been delivered.
	 * If this throws, the session is closed without onSessionClose() being called.
	 *
	 * @param InternetAddress $address       Where the transport reaches the peer; a relay's address if it connects through one.
	 * @param string|null     $clientAddress IPv4 or IPv6 address the offer was signaled from, or the one a trusted
	 *                                       reverse proxy named, where the signaling transport knew it. Always in
	 *                                       canonical form: unbracketed, IPv6 lowercase and zero-compressed.
	 */
	public function onSessionOpen(int $sessionId, string $networkId, InternetAddress $address, ?string $clientAddress, ?PeerIdentity $identity) : void;

	public function onSessionClose(int $sessionId, DisconnectReason $reason) : void;

	/**
	 * Asked before every packet is read. Returning false leaves the packet in the client's receive queue.
	 */
	public function canAcceptPackets() : bool;

	public function onPacketReceive(int $sessionId, string $payload, Reliability $reliability) : void;

	/**
	 * Called when a packet sent with a receipt ID has left the send queue.
	 */
	public function onPacketAck(int $sessionId, int $receiptId) : void;

	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff) : void;

	public function onPingMeasure(int $sessionId, int $pingMs) : void;
}
