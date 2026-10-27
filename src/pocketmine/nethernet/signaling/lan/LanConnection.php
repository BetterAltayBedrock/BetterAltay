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

namespace pocketmine\nethernet\signaling\lan;

use pocketmine\nethernet\negotiation\Negotiation;
use pocketmine\nethernet\signaling\Signal;
use pocketmine\nethernet\signaling\SignalType;
use pocketmine\nethernet\utils\InternetAddress;

/**
 * @internal
 */
final class LanConnection{

	private bool $answerSent = false;

	public function __construct(
		public readonly Negotiation $negotiation,
		public readonly int $peerId,
		public readonly InternetAddress $address,
		public readonly string $connectionId
	){}

	/**
	 * Takes the signals the peer is now owed.
	 *
	 * @return Signal[]
	 * @phpstan-return list<Signal>
	 */
	public function takeSignals() : array{
		if($this->negotiation->isFailed()){
			return [Signal::error($this->connectionId, $this->negotiation->getFailureCode())];
		}

		$signals = [];
		if(!$this->answerSent){
			$answer = $this->negotiation->getAnswer();
			if($answer === null){
				/* A candidate refers to the answer it belongs to, so none goes out before the answer has. */
				return [];
			}
			$signals[] = new Signal(SignalType::CONNECT_RESPONSE, $this->connectionId, $answer);
			$this->answerSent = true;
		}
		foreach($this->negotiation->takeLocalCandidates() as $candidate){
			$signals[] = new Signal(SignalType::CANDIDATE_ADD, $this->connectionId, $candidate);
		}

		return $signals;
	}
}
