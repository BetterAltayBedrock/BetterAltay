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

use pocketmine\nethernet\sdp\Fingerprint;
use function time;

/**
 * Issues assertions whose token the host signs itself, so they vouch for nothing but the host's key.
 */
final class SelfSignedIdentityProvider implements IdentityProvider{

	public const DEFAULT_DOMAIN = "self";
	public const DEFAULT_TOKEN_LIFETIME = 60;

	/**
	 * @param string $domain Domain advertised in the assertion's `idp` object.
	 */
	public function __construct(
		private readonly ServerIdentity $identity,
		private readonly string $domain = self::DEFAULT_DOMAIN,
		private readonly int $tokenLifetimeSeconds = self::DEFAULT_TOKEN_LIFETIME
	){
		if($domain === ""){
			throw new \InvalidArgumentException("Identity provider domain must not be empty");
		}
		if($tokenLifetimeSeconds < 1){
			throw new \InvalidArgumentException("Token lifetime must be positive, got $tokenLifetimeSeconds");
		}
	}

	public function issue(Fingerprint $localFingerprint) : IdentityAssertion{
		$issuedAt = time();
		$encodedKey = $this->identity->getPublicKey()->toBase64Der();

		$token = JsonWebToken::create(
			[JsonWebToken::HEADER_PUBLIC_KEY => $encodedKey],
			[
				JsonWebToken::CLAIM_EXPIRY => $issuedAt + $this->tokenLifetimeSeconds,
				JsonWebToken::CLAIM_ISSUED_AT => $issuedAt,
				JsonWebToken::CLAIM_PUBLIC_KEY => $encodedKey
			],
			$this->identity
		);

		return IdentityAssertion::create(
			$this->domain,
			$token,
			JsonWebSignature::signDetached($localFingerprint->toCanonicalPayload(), $this->identity)
		);
	}
}
