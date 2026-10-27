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

namespace pocketmine\nethernet\identity;

final class PeerIdentity{

	/**
	 * @param PublicKey    $publicKey      The peer's verified public key, bound to this connection's DTLS fingerprint.
	 * @param string       $providerDomain Identity provider domain claimed in the assertion (unverified, for informational/display purposes only).
	 * @param JsonWebToken $token          The token the peer presented. Its claims are only as trustworthy as the {@link TokenVerifier} that accepted it.
	 */
	public function __construct(
		public readonly PublicKey $publicKey,
		public readonly string $providerDomain,
		public readonly JsonWebToken $token
	){}
}
