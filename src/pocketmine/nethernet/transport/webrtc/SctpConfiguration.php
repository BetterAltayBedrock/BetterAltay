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

namespace pocketmine\nethernet\transport\webrtc;

use function pmmp\webrtc\set_sctp_settings;

/**
 * SCTP heartbeat and retransmission timer configuration.
 *
 * These settings control how quickly unresponsive peers are dropped. They are process-wide: once applied with
 * {@see SctpConfiguration::applyGlobally()} they govern every association created afterwards, whichever server it
 * belongs to, so they are applied by whoever owns the process rather than by a server.
 * Any property left null retains the libdatachannel default.
 */
final class SctpConfiguration{

	/**
	 * @param int|null $heartbeatInterval        Heartbeat interval in milliseconds for idle associations.
	 * @param int|null $maxRetransmitAttempts    Maximum consecutive unacknowledged heartbeats or retransmissions before aborting.
	 * @param int|null $minRetransmitTimeout     Minimum retransmission timeout in milliseconds.
	 * @param int|null $maxRetransmitTimeout     Maximum retransmission timeout in milliseconds.
	 * @param int|null $initialRetransmitTimeout Initial retransmission timeout in milliseconds before RTT is measured.
	 */
	public function __construct(
		public readonly ?int $heartbeatInterval = null,
		public readonly ?int $maxRetransmitAttempts = null,
		public readonly ?int $minRetransmitTimeout = null,
		public readonly ?int $maxRetransmitTimeout = null,
		public readonly ?int $initialRetransmitTimeout = null
	){
		foreach([
			"Heartbeat interval" => $heartbeatInterval,
			"Maximum retransmit attempts" => $maxRetransmitAttempts,
			"Minimum retransmit timeout" => $minRetransmitTimeout,
			"Maximum retransmit timeout" => $maxRetransmitTimeout,
			"Initial retransmit timeout" => $initialRetransmitTimeout
		] as $name => $value){
			if($value !== null && $value < 1){
				throw new \InvalidArgumentException("$name must be positive, got $value");
			}
		}
		if($minRetransmitTimeout !== null && $maxRetransmitTimeout !== null && $minRetransmitTimeout > $maxRetransmitTimeout){
			throw new \InvalidArgumentException("Minimum retransmit timeout ($minRetransmitTimeout) cannot be greater than the maximum ($maxRetransmitTimeout)");
		}
	}

	/**
	 * Applies these settings to all SCTP associations created in this process from now on.
	 */
	public function applyGlobally() : void{
		set_sctp_settings(
			$this->heartbeatInterval,
			$this->maxRetransmitAttempts,
			$this->minRetransmitTimeout,
			$this->maxRetransmitTimeout,
			$this->initialRetransmitTimeout
		);
	}
}
