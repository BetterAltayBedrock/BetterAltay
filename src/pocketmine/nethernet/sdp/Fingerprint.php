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

namespace pocketmine\nethernet\sdp;

use function count;
use function explode;
use function preg_match;
use function strcasecmp;

/**
 * Represents a DTLS certificate fingerprint from an SDP `a=fingerprint` attribute (RFC 8122 / RFC 8827).
 */
final class Fingerprint{

	private const ALGORITHM = "sha-256";
	private const DIGEST_PATTERN = '/^[0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2})*$/';

	/**
	 * @throws SdpException
	 */
	public function __construct(
		private readonly string $algorithm,
		private readonly string $digest
	){
		if(strcasecmp($algorithm, self::ALGORITHM) !== 0){
			throw new SdpException("Fingerprint algorithm must be " . self::ALGORITHM . ", got: $algorithm");
		}
		if(preg_match(self::DIGEST_PATTERN, $digest) !== 1){
			throw new SdpException("Fingerprint digest is not colon-separated hex: $digest");
		}
	}

	/**
	 * @throws SdpException
	 */
	public static function parse(string $value) : self{
		$parts = explode(" ", $value, limit: 3);
		if(count($parts) !== 2){
			throw new SdpException("Expected an algorithm and a digest separated by one space, got: $value");
		}

		return new self($parts[0], $parts[1]);
	}

	public function getAlgorithm() : string{ return $this->algorithm; }

	public function getDigest() : string{ return $this->digest; }

	public function equals(self $other) : bool{
		return strcasecmp($this->algorithm, $other->algorithm) === 0 && strcasecmp($this->digest, $other->digest) === 0;
	}

	public function toAttributeValue() : string{
		return $this->algorithm . " " . $this->digest;
	}

	/**
	 * Generates the Canonical JSON payload (RFC 8785 subset) used in detached JWS fingerprint signatures.
	 */
	public function toCanonicalPayload() : string{
		return '{"fingerprint":[{"algorithm":"' . $this->algorithm . '","digest":"' . $this->digest . '"}]}';
	}
}
