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

namespace pocketmine\nethernet\transport;

use pocketmine\nethernet\protocol\Reliability;

interface MessageChannel{

	public function getLabel() : string;

	/**
	 * Returns null if the channel's label or delivery guarantees match neither NetherNet channel.
	 */
	public function getReliability() : ?Reliability;

	public function isOpen() : bool;

	public function isClosed() : bool;

	public function getMaxMessageSize() : int;

	/**
	 * @throws TransportException
	 */
	public function send(string $message) : void;

	/**
	 * @throws TransportException
	 */
	public function receive() : ?string;

	/**
	 * Returns the bytes queued for sending that have not left yet.
	 */
	public function getBufferedAmount() : int;

	/**
	 * Returns the bytes received and not yet taken with receive().
	 */
	public function getAvailableAmount() : int;

	public function getQueuedMessageCount() : int;

	/**
	 * Never throws.
	 */
	public function close() : void;
}
