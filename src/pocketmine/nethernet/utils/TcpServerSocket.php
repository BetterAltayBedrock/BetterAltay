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

namespace pocketmine\nethernet\utils;

use function socket_accept;
use function socket_bind;
use function socket_clear_error;
use function socket_close;
use function socket_create;
use function socket_getpeername;
use function socket_getsockname;
use function socket_last_error;
use function socket_listen;
use function socket_set_nonblock;
use function socket_set_option;
use const AF_INET;
use const AF_INET6;
use const IPPROTO_IPV6;
use const IPV6_V6ONLY;
use const SO_REUSEADDR;
use const SOCK_STREAM;
use const SOCKET_EWOULDBLOCK;
use const SOL_SOCKET;
use const SOL_TCP;

/**
 * @internal
 * Non-blocking listening TCP socket.
 */
final class TcpServerSocket{

	private const BACKLOG = 128;

	private readonly \Socket $socket;
	private readonly InternetAddress $bindAddress;
	private bool $closed = false;

	/**
	 * @param InternetAddress $address Address to listen on; port 0 lets the system choose one.
	 *
	 * @throws SocketException
	 */
	public function __construct(InternetAddress $address){
		$ipv6 = $address->version === 6;
		$socket = @socket_create($ipv6 ? AF_INET6 : AF_INET, SOCK_STREAM, SOL_TCP);
		if($socket === false){
			throw SocketException::fromErrno("Could not create a TCP socket", socket_last_error());
		}

		@socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
		if($ipv6){
			@socket_set_option($socket, IPPROTO_IPV6, IPV6_V6ONLY, 1);
		}

		if(!@socket_bind($socket, $address->ip, $address->port) || !@socket_listen($socket, self::BACKLOG)){
			$errno = socket_last_error($socket);
			socket_close($socket);

			throw SocketException::fromErrno("Could not listen on $address", $errno);
		}

		socket_set_nonblock($socket);
		$this->socket = $socket;

		$ip = "";
		$port = 0;
		$this->bindAddress = @socket_getsockname($socket, $ip, $port) ? new InternetAddress($ip, $port, $address->version) : $address;
	}

	/**
	 * Returns the address actually bound, with the port the system chose if none was asked for.
	 */
	public function getBindAddress() : InternetAddress{ return $this->bindAddress; }

	/**
	 * Takes the next waiting connection, or returns null if there is none.
	 *
	 * @phpstan-return array{\Socket, ?InternetAddress}|null the connection and its peer's address, where that can be read
	 *
	 * @throws SocketException
	 */
	public function accept() : ?array{
		/*
		 * A failed accept reports through the global error slot, not the listener's, and the slot keeps whatever
		 * an earlier call on any socket left in it. It is cleared on both sides so that only this call is judged.
		 */
		socket_clear_error();
		$accepted = @socket_accept($this->socket);
		if($accepted === false){
			$errno = socket_last_error();
			socket_clear_error();
			if($errno === 0 || $errno === SOCKET_EWOULDBLOCK){
				return null;
			}

			throw SocketException::fromErrno("Accept failed", $errno);
		}

		$ip = "";
		$port = 0;

		return [$accepted, @socket_getpeername($accepted, $ip, $port) ? new InternetAddress($ip, $port, $this->bindAddress->version) : null];
	}

	public function close() : void{
		if(!$this->closed){
			$this->closed = true;
			socket_close($this->socket);
		}
	}
}
