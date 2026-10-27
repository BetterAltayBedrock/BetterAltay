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

use pocketmine\nethernet\negotiation\Negotiator;
use pocketmine\nethernet\utils\AddressBlockList;

final class SignalingContext{

	public function __construct(
		public readonly Negotiator $negotiator,
		public readonly AddressBlockList $blockList,
		public readonly \Logger $logger
	){}
}
