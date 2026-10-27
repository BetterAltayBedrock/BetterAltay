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

use pocketmine\nethernet\protocol\Reliability;

final class ChannelPair{

	public function __construct(
		public readonly MessageChannel $reliable,
		public readonly MessageChannel $unreliable
	){}

	public function get(Reliability $reliability) : MessageChannel{
		return match($reliability){
			Reliability::RELIABLE => $this->reliable,
			Reliability::UNRELIABLE => $this->unreliable
		};
	}

	/**
	 * @return MessageChannel[]
	 * @phpstan-return list<MessageChannel>
	 */
	public function all() : array{
		return [$this->reliable, $this->unreliable];
	}
}
