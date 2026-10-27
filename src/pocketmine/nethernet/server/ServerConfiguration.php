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

use pocketmine\nethernet\identity\AssertionIdentityVerifier;
use pocketmine\nethernet\identity\IdentityProvider;
use pocketmine\nethernet\negotiation\TransportNegotiator;
use pocketmine\nethernet\transport\ConnectionBudgetConfiguration;
use pocketmine\nethernet\transport\PeerTransportFactory;
use pocketmine\nethernet\transport\webrtc\WebRtcTransportFactory;
use pocketmine\nethernet\utils\InternetAddress;

final class ServerConfiguration{

	public const DEFAULT_GATHERING_TIMEOUT = 15.0;
	public const DEFAULT_CHANNEL_TIMEOUT = 5.0;

	/**
	 * @param IdentityProvider $identityProvider          Issues server identity assertions (`a=identity`) for SDP answers.
	 * @param float            $gatheringTimeout          Timeout in seconds for ICE candidate gathering.
	 * @param float            $channelTimeout            Timeout in seconds for data channels to open.
	 * @param int              $maxRemoteCandidates       Maximum ICE candidates a peer may offer or trickle per connection.
	 * @param int|null         $maxNegotiations           Maximum connections being negotiated at once, across all signaling,
	 *                                                    or null for no limit.
	 * @param int|null         $maxNegotiationsPerAddress Maximum connections one address may be negotiating at once, or
	 *                                                    null for no limit. An address that goes over is blocked.
	 * @param string[]|null    $advertisedAddresses       Public IP addresses advertised to connecting clients in bundled answers,
	 *                                                    or null to advertise all gathered candidates. Unbound addresses are announced
	 *                                                    as server-reflexive candidates for 1:1 NAT port forwarding.
	 * @phpstan-param list<string>|null $advertisedAddresses
	 */
	public function __construct(
		public readonly IdentityProvider $identityProvider,
		public readonly \Logger $logger,
		public readonly AssertionIdentityVerifier $identityVerifier = new AssertionIdentityVerifier(),
		public readonly PeerTransportFactory $transportFactory = new WebRtcTransportFactory(),
		public readonly ConnectionBudgetConfiguration $budget = new ConnectionBudgetConfiguration(),
		public readonly float $gatheringTimeout = self::DEFAULT_GATHERING_TIMEOUT,
		public readonly float $channelTimeout = self::DEFAULT_CHANNEL_TIMEOUT,
		public readonly int $maxRemoteCandidates = TransportNegotiator::DEFAULT_MAX_REMOTE_CANDIDATES,
		public readonly ?int $maxNegotiations = TransportNegotiator::DEFAULT_MAX_NEGOTIATIONS,
		public readonly ?int $maxNegotiationsPerAddress = TransportNegotiator::DEFAULT_MAX_NEGOTIATIONS_PER_ADDRESS,
		public readonly ?array $advertisedAddresses = null
	){
		if($maxRemoteCandidates < 1){
			throw new \InvalidArgumentException("Maximum remote candidates must be positive, got $maxRemoteCandidates");
		}
		if($maxNegotiations !== null && $maxNegotiations < 1){
			throw new \InvalidArgumentException("Maximum negotiations must be positive or null, got $maxNegotiations");
		}
		if($maxNegotiationsPerAddress !== null && $maxNegotiationsPerAddress < 1){
			throw new \InvalidArgumentException("Maximum negotiations per address must be positive or null, got $maxNegotiationsPerAddress");
		}
		if($maxNegotiations !== null && $maxNegotiationsPerAddress !== null && $maxNegotiationsPerAddress > $maxNegotiations){
			throw new \InvalidArgumentException("Maximum negotiations per address ($maxNegotiationsPerAddress) cannot be greater than the maximum negotiations ($maxNegotiations)");
		}
		if($gatheringTimeout <= 0.0 || $channelTimeout <= 0.0){
			throw new \InvalidArgumentException("Timeouts must be positive");
		}
		foreach($advertisedAddresses ?? [] as $address){
			if(InternetAddress::normalizeIp($address) === null){
				throw new \InvalidArgumentException("Advertised address \"$address\" is not an IP address");
			}
		}
	}
}
