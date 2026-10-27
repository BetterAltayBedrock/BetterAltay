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

use pocketmine\nethernet\identity\PeerIdentity;
use pocketmine\nethernet\transport\ChannelPair;
use pocketmine\nethernet\transport\PeerTransport;
use pocketmine\nethernet\utils\InternetAddress;

/**
 * Whoever takes it from the negotiator owns the transport and must close it.
 *
 * The address is where the transport reaches the peer, which is a relay's when the peer connects through one. The
 * client address is the IP the offer was signaled from, where the signaling transport knew it, in the canonical
 * form {@link InternetAddress::normalizeIp()} gives.
 */
final class EstablishedPeer{

	public function __construct(
		public readonly PeerTransport $transport,
		public readonly ChannelPair $channels,
		public readonly string $networkId,
		public readonly InternetAddress $address,
		public readonly ?string $clientAddress,
		public readonly ?PeerIdentity $identity
	){}
}
