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

namespace pocketmine\nethernet\signaling\lan\packet;

use pmmp\encoding\ByteBufferWriter;

abstract class Packet{

	/**
	 * @param int $senderId 64-bit unsigned NetworkID of the host the packet comes from.
	 */
	public function __construct(
		public readonly int $senderId
	){}

	abstract public function getType() : PacketType;

	abstract public function encodeBody(ByteBufferWriter $out) : void;
}
