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

namespace pocketmine\nethernet\negotiation;

use function count;
use function explode;
use function filter_var;
use function implode;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use const FILTER_VALIDATE_IP;

/**
 * @internal
 * The fields of an SDP candidate attribute that NetherNet signals.
 */
final class IceCandidate{

	private const TYPE_HOST = "host";
	private const TYPE_SERVER_REFLEXIVE = "srflx";
	private const TYPE_RELAY = "relay";

	private const COMPONENT = "1";

	/** RFC 8445 section 5.1.2.1 priority for a server-reflexive candidate */
	public const SERVER_REFLEXIVE_PRIORITY = (100 << 24) + (65535 << 8) + 255;

	public function __construct(
		public readonly string $foundation,
		public readonly string $protocol,
		public readonly string $priority,
		public readonly string $address,
		public readonly string $port,
		public readonly string $type,
		public readonly ?string $relatedAddress = null,
		public readonly ?string $relatedPort = null
	){}

	/**
	 * @param string $value Candidate attribute value, without the "candidate:" prefix.
	 */
	public static function parse(string $value) : ?self{
		$parts = explode(" ", $value);
		if(count($parts) < 8 || $parts[6] !== "typ"){
			return null;
		}

		$related = [];
		for($i = 8; $i + 1 < count($parts); $i += 2){
			$related[$parts[$i]] = $parts[$i + 1];
		}

		return new self($parts[0], $parts[2], $parts[3], $parts[4], $parts[5], $parts[7], $related["raddr"] ?? null, $related["rport"] ?? null);
	}

	/**
	 * Whether the candidate names an IP address, in a form the peer connection cannot read as a hostname to look up.
	 */
	public static function isAcceptableFromPeer(string $value) : bool{
		foreach(["a=", "candidate:"] as $prefix){
			if(str_starts_with($value, $prefix)){
				$value = substr($value, strlen($prefix));
			}
		}

		// Matched the way the peer connection reads the fields, so that both agree on which one is the address.
		if(preg_match('/^\S+ \d+ \S+ \d+ (\S+) \d{1,5} typ \S+(?: \S+)*$/D', rtrim($value), $matches) !== 1){
			return false;
		}

		return filter_var($matches[1], FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Builds a candidate for an address this host is reached at but did not gather. What it was derived from is left
	 * unspecified, which keeps the host's own address private.
	 */
	public static function serverReflexive(string $foundation, string $address, string $port, int $priority = self::SERVER_REFLEXIVE_PRIORITY) : self{
		return new self($foundation, "udp", (string) $priority, $address, $port, self::TYPE_SERVER_REFLEXIVE);
	}

	public function withoutRelatedAddress() : self{
		return new self($this->foundation, $this->protocol, $this->priority, $this->address, $this->port, $this->type);
	}

	public function isHost() : bool{
		return $this->type === self::TYPE_HOST;
	}

	public function isUdpHost() : bool{
		return $this->isHost() && strtolower($this->protocol) === "udp";
	}

	public function isIpv6() : bool{
		return str_contains($this->address, ":");
	}

	/**
	 * Returns the attribute value, without the "candidate:" prefix. A reflexive or relayed candidate names the address
	 * it was derived from, as unspecified when it has none to name.
	 */
	public function toString() : string{
		$parts = [$this->foundation, self::COMPONENT, $this->protocol, $this->priority, $this->address, $this->port, "typ", $this->type];
		if($this->type === self::TYPE_SERVER_REFLEXIVE || $this->type === self::TYPE_RELAY){
			$parts[] = "raddr";
			$parts[] = $this->relatedAddress ?? ($this->isIpv6() ? "::" : "0.0.0.0");
			$parts[] = "rport";
			$parts[] = $this->relatedPort ?? "0";
		}

		return implode(" ", $parts);
	}
}
