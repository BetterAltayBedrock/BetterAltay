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

namespace pocketmine\nethernet\utils;

use const INF;

/**
 * @internal
 */
final class AddressBlockTracker implements AddressBlockList{

	/** @phpstan-var array<string, float> */
	private array $blocked = [];

	private float $now = 0.0;
	private float $nextExpiry = INF;

	/**
	 * @param float $until Timestamp at which the block expires, or INF to block until {@link AddressBlockTracker::unblock()}.
	 */
	public function block(string $address, float $until = INF) : void{
		$address = self::key($address);
		if(($this->blocked[$address] ?? 0.0) >= $until){
			return;
		}

		$this->blocked[$address] = $until;
		if($until < $this->nextExpiry){
			$this->nextExpiry = $until;
		}
	}

	public function unblock(string $address) : void{
		unset($this->blocked[self::key($address)]);
	}

	public function isBlocked(string $address) : bool{
		$until = $this->blocked[self::key($address)] ?? null;

		return $until !== null && $until > $this->now;
	}

	public function update(float $now) : void{
		$this->now = $now;
		if($now < $this->nextExpiry){
			return;
		}

		$this->nextExpiry = INF;
		foreach($this->blocked as $address => $until){
			if($until <= $now){
				unset($this->blocked[$address]);
			}elseif($until < $this->nextExpiry){
				$this->nextExpiry = $until;
			}
		}
	}

	/** Equivalent spellings of one address (IPv6 zero compression, brackets) must share a single entry. */
	private static function key(string $address) : string{
		return InternetAddress::normalizeIp($address) ?? $address;
	}
}
