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

enum TransportState{
	case CONNECTING;
	case CONNECTED;
	/**
	 * Connectivity was lost and may still come back.
	 */
	case DISCONNECTED;
	case FAILED;
	case CLOSED;

	public function isBroken() : bool{
		return $this === self::FAILED || $this === self::CLOSED;
	}
}
