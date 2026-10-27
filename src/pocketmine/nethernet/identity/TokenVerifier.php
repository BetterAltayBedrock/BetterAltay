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

/**
 * Verifies whether a peer's identity token is acceptable.
 *
 * Implementations can validate token signatures, issuers, or custom authorization policies.
 *
 * If no verifier is provided to {@link AssertionIdentityVerifier}, any well-formed, unexpired token
 * is accepted.
 *
 * @see SelfSignedTokenVerifier
 */
interface TokenVerifier{

	/**
	 * @throws IdentityException if the token fails verification.
	 */
	public function check(JsonWebToken $token) : void;
}
