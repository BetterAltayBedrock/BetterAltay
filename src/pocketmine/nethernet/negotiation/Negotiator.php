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

interface Negotiator{

	/**
	 * @param string|null $peerAddress IPv4 or IPv6 address the offer was received from, in any spelling, if the
	 *                                 signaling transport knows it. It is offered to the peer connection as a
	 *                                 candidate so that a peer behind NAT can be reached through a TURN relay, and
	 *                                 is reported as the client address of the session the offer leads to.
	 *
	 * @throws NegotiationException if the offer cannot be accepted.
	 * @throws \InvalidArgumentException if the peer address is not an IPv4 or IPv6 address.
	 */
	public function beginNegotiation(string $offerSdp, string $networkId, CandidateMode $candidateMode = CandidateMode::BUNDLED, ?string $peerAddress = null) : Negotiation;

	public function tick(float $now) : void;

	/**
	 * Drains the peers established since the last call.
	 *
	 * @return EstablishedPeer[]
	 * @phpstan-return list<EstablishedPeer>
	 */
	public function takeEstablished() : array;

	/**
	 * Drains the addresses that went over their share of negotiations since the last call, for the server to block.
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function takeFloodingAddresses() : array;

	public function shutdown() : void;
}
