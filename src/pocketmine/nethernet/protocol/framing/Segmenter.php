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

use function chr;
use function intdiv;
use function strlen;
use function substr;

/**
 * @internal
 * Splits message payloads into framed data channel fragments prefixed with a 1-byte countdown header.
 */
final class Segmenter{

	/** Size in bytes of the countdown header that prefixes every fragment. */
	public const HEADER_SIZE = 1;

	/** Maximum payload bytes per fragment under standard SCTP max message size (262,144 bytes minus 1-byte header). */
	public const MAX_SEGMENT_PAYLOAD_SIZE = 262143;

	/** Maximum number of fragments supported per message in vanilla NetherNet. */
	public const MAX_SEGMENTS = 255;

	/** Maximum total message payload size expressible across 255 fragments. */
	public const MAX_PAYLOAD_SIZE = self::MAX_SEGMENT_PAYLOAD_SIZE * self::MAX_SEGMENTS;

	public function __construct(
		private readonly int $maxSegmentPayloadSize = self::MAX_SEGMENT_PAYLOAD_SIZE
	){
		if($maxSegmentPayloadSize < 1 || $maxSegmentPayloadSize > self::MAX_SEGMENT_PAYLOAD_SIZE){
			throw new \InvalidArgumentException("Segment payload size must be between 1 and " . self::MAX_SEGMENT_PAYLOAD_SIZE . ", got $maxSegmentPayloadSize");
		}
	}

	public function getMaxSegmentPayloadSize() : int{ return $this->maxSegmentPayloadSize; }

	/**
	 * Fragments a payload into prefixed chunks carrying countdown headers (`N-1` down to `0x00`).
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 *
	 * @throws FramingException if the payload exceeds the 255 fragment limit.
	 */
	public function segment(string $payload) : array{
		$length = strlen($payload);
		if($length === 0){
			return [];
		}

		$total = intdiv($length - 1, $this->maxSegmentPayloadSize) + 1;
		if($total > self::MAX_SEGMENTS){
			throw new FramingException("Payload of $length bytes needs $total segments, but at most " . self::MAX_SEGMENTS . " can be framed");
		}

		$segments = [];
		$remaining = $total - 1;
		for($offset = 0; $offset < $length; $offset += $this->maxSegmentPayloadSize){
			$segments[] = chr($remaining) . substr($payload, $offset, $this->maxSegmentPayloadSize);
			--$remaining;
		}

		return $segments;
	}
}
