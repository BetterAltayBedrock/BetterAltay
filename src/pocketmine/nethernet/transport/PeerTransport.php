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

use pocketmine\nethernet\utils\InternetAddress;

interface PeerTransport{

	/**
	 * @throws TransportException
	 */
	public function setRemoteOffer(string $sdp) : void;

	/**
	 * @throws TransportException
	 */
	public function getLocalDescription() : ?string;

	/**
	 * @throws TransportException
	 */
	public function isGatheringComplete() : bool;

	/**
	 * @throws TransportException
	 */
	public function addRemoteCandidate(string $candidate) : void;

	/**
	 * Drains the local candidates gathered since the last call.
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 *
	 * @throws TransportException
	 */
	public function pollLocalCandidates() : array;

	/**
	 * Drains the channels the peer has opened since the last call.
	 *
	 * @return MessageChannel[]
	 * @phpstan-return list<MessageChannel>
	 *
	 * @throws TransportException
	 */
	public function pollChannels() : array;

	public function getState() : TransportState;

	/**
	 * Returns whether connectivity was lost, as opposed to an orderly close. Still answered after close().
	 */
	public function hasLostConnectivity() : bool;

	/**
	 * The last known address is kept after close().
	 */
	public function getRemoteAddress() : ?InternetAddress;

	public function getRoundTripTime() : ?int;

	/**
	 * The final count is kept after close().
	 */
	public function getBytesSent() : int;

	/**
	 * The final count is kept after close().
	 */
	public function getBytesReceived() : int;

	/**
	 * Never throws.
	 */
	public function close() : void;
}
