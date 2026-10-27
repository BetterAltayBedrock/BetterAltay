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

/**
 * @internal
 */
enum NegotiationState{

	case GATHERING;

	/**
	 * SDP answer generated, waiting for remote peer to open required SCTP data channels.
	 */
	case ANSWERED;
	case ESTABLISHED;
	case FAILED;

	public function isFinished() : bool{
		return $this === self::ESTABLISHED || $this === self::FAILED;
	}
}
