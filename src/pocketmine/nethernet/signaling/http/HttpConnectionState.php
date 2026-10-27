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

/**
 * @internal
 */
enum HttpConnectionState{
	/**
	 * On a TLS listener, waiting for the first byte to tell a TLS client from a plain HTTP one.
	 */
	case SNIFFING;
	case TLS_HANDSHAKE;
	case READING_HEAD;
	/**
	 * A plain HTTP request on a TLS listener; its head is read only to redirect it.
	 */
	case READING_HEAD_TO_REDIRECT;
	case READING_BODY;
	case NEGOTIATING;
	case WRITING;
	case DONE;

	/**
	 * Returns whether the client has yet to deliver a complete request.
	 */
	public function isAwaitingRequest() : bool{
		return match($this){
			self::SNIFFING, self::TLS_HANDSHAKE, self::READING_HEAD, self::READING_HEAD_TO_REDIRECT, self::READING_BODY => true,
			self::NEGOTIATING, self::WRITING, self::DONE => false
		};
	}
}
