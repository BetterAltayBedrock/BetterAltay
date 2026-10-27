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

namespace pocketmine\nethernet\negotiation;

use pocketmine\nethernet\protocol\ErrorCode;

interface Negotiation{

	/**
	 * Returns the SDP answer to send back to the peer, or null if gathering/generation is pending.
	 */
	public function getAnswer() : ?string;

	public function isFinished() : bool;

	public function isFailed() : bool;

	/**
	 * Returns whether the peer connection is gone: the negotiation failed, or the connection it established has
	 * since been closed.
	 */
	public function isClosed() : bool;

	public function getFailureReason() : ?string;

	public function getFailureCode() : ErrorCode;

	/**
	 * @throws NegotiationException
	 */
	public function addRemoteCandidate(string $candidate) : void;

	/**
	 * Drains the local ICE candidates gathered since the last call.
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function takeLocalCandidates() : array;

	public function fail(string $reason, ErrorCode $code = ErrorCode::GENERIC_FAILURE) : void;

	/**
	 * Closes the peer connection, including one that is established and has been handed over.
	 */
	public function close() : void;
}
