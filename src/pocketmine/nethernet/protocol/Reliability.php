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

namespace pocketmine\nethernet\protocol;

enum Reliability{

	/**
	 * Ordered, lossless SCTP data channel with fragmentation support for game data.
	 */
	case RELIABLE;

	/**
	 * Unordered, loss-tolerant SCTP data channel (maxRetransmits=0, no fragmentation) for latency-sensitive data.
	 */
	case UNRELIABLE;

	public function getChannelLabel() : string{
		return match($this){
			self::RELIABLE => "ReliableDataChannel",
			self::UNRELIABLE => "UnreliableDataChannel"
		};
	}

	public function isFragmentationSupported() : bool{
		return $this === self::RELIABLE;
	}

	public static function fromChannelLabel(string $label) : ?self{
		foreach(self::cases() as $case){
			if($case->getChannelLabel() === $label){
				return $case;
			}
		}

		return null;
	}
}
