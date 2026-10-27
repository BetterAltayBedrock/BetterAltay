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

use pocketmine\nethernet\protocol\DisconnectReason;
use pocketmine\nethernet\protocol\Reliability;

/**
 * Commands naming a session that no longer exists are ignored.
 */
interface ServerInterface{

	/**
	 * A payload too large to send closes the session with {@link DisconnectReason::SEND_FAILED} if it was to be
	 * reliable, and is dropped if it was not.
	 */
	public function sendPacket(int $sessionId, string $payload, Reliability $reliability = Reliability::RELIABLE, ?int $receiptId = null) : void;

	/**
	 * Disconnects a client once the packets already queued for it have been sent.
	 */
	public function closeSession(int $sessionId, DisconnectReason $reason = DisconnectReason::SERVER_DISCONNECT) : void;

	/**
	 * Refuses new connections from the address and disconnects the sessions that either connect from it or were
	 * signaled from it.
	 *
	 * @param int $timeout Seconds until the address is unblocked, or -1 to block it until unblockAddress() is called.
	 */
	public function blockAddress(string $address, int $timeout = 300) : void;

	public function unblockAddress(string $address) : void;
}
