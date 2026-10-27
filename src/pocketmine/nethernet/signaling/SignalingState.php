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

namespace pocketmine\nethernet\signaling;

enum SignalingState{
	case CREATED;
	case STARTED;
	case SHUT_DOWN;

	/**
	 * @throws \LogicException
	 */
	public function checkCanStart() : void{
		if($this !== self::CREATED){
			throw new \LogicException($this === self::STARTED ? "Signaling transport is already started" : "Signaling transport has been shut down and cannot be restarted");
		}
	}
}
