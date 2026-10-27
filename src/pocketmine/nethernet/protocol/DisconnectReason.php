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

namespace pocketmine\nethernet\protocol;

enum DisconnectReason : int{

	/**
	 * The remote client closed the connection or data channels, or the SCTP association was lost to missed heartbeats.
	 */
	case PEER_DISCONNECT = 0;
	case SERVER_DISCONNECT = 1;
	case SERVER_SHUTDOWN = 2;
	case REJECTED_BY_HOST = 3;

	/**
	 * The underlying WebRTC peer connection failed (e.g. ICE consent check timeout).
	 */
	case CONNECTION_FAILED = 4;
	case BAD_DATA = 5;
	case SEND_FAILED = 6;
	case RECEIVE_QUEUE_TOO_MANY_BYTES = 7;
	case RECEIVE_QUEUE_TOO_MANY_MESSAGES = 8;
	case SEND_QUEUE_TOO_MANY_BYTES = 9;
	case ADDRESS_BLOCKED = 10;

	public function getMessage() : string{
		return match($this){
			self::PEER_DISCONNECT => "client disconnect",
			self::SERVER_DISCONNECT => "server disconnect",
			self::SERVER_SHUTDOWN => "server shutdown",
			self::REJECTED_BY_HOST => "rejected by the host",
			self::CONNECTION_FAILED => "connection failed",
			self::BAD_DATA => "bad data received",
			self::SEND_FAILED => "failed to send",
			self::RECEIVE_QUEUE_TOO_MANY_BYTES => "too many unread bytes queued",
			self::RECEIVE_QUEUE_TOO_MANY_MESSAGES => "too many unread messages queued",
			self::SEND_QUEUE_TOO_MANY_BYTES => "too many unsent bytes queued",
			self::ADDRESS_BLOCKED => "address blocked"
		};
	}
}
