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

namespace pocketmine\nethernet\protocol\framing;

use function ord;
use function strlen;
use function substr;

/**
 * @internal
 * Reassembles fragmented data channel messages using NetherNet 1-byte countdown framing headers.
 */
final class Assembler{

	private string $buffer = "";

	/** Remaining fragments expected for the current message sequence (0 = unfragmented/complete or awaiting start). */
	private int $remaining = 0;

	public function __construct(
		private readonly bool $fragmentationAllowed,
		private readonly int $maxPayloadSize = Segmenter::MAX_PAYLOAD_SIZE
	){
		if($maxPayloadSize < 1){
			throw new \InvalidArgumentException("Maximum payload size must be positive, got $maxPayloadSize");
		}
	}

	private function reset() : void{
		$this->buffer = "";
		$this->remaining = 0;
	}

	/**
	 * Processes an incoming fragment and returns the assembled payload when complete (header 0x00), or null if more fragments remain.
	 *
	 * @throws FramingException
	 */
	public function accept(string $segment) : ?string{
		$length = strlen($segment);
		if($length < 2){
			$this->reset();
			throw new FramingException("Segment must have a counter byte and at least one payload byte, got $length bytes");
		}

		$remaining = ord($segment[0]);
		if(!$this->fragmentationAllowed && $remaining !== 0){
			$this->reset();
			throw new FramingException("Channel does not support fragmentation, but received a segment with remaining count $remaining");
		}
		if($this->remaining > 0 && $remaining !== $this->remaining - 1){
			$expected = $this->remaining - 1;
			$this->reset();
			throw new FramingException("Expected segment countdown $expected, got $remaining");
		}

		if(strlen($this->buffer) + $length - 1 > $this->maxPayloadSize){
			$this->reset();
			throw new FramingException("Assembled payload would exceed the $this->maxPayloadSize byte limit");
		}

		$this->buffer .= substr($segment, 1);
		$this->remaining = $remaining;

		if($remaining !== 0){
			return null;
		}

		$payload = $this->buffer;
		$this->buffer = "";

		return $payload;
	}
}
