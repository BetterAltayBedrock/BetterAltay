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

use function ctype_digit;
use function filter_var;
use function inet_ntop;
use function inet_pton;
use function str_ends_with;
use function str_starts_with;
use function strrpos;
use function substr;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final class InternetAddress{

	public readonly string $ip;

	/**
	 * @throws \InvalidArgumentException
	 */
	public function __construct(
		string $ip,
		public readonly int $port,
		public readonly int $version
	){
		if($port < 0 || $port > 65535){
			throw new \InvalidArgumentException("Port must be between 0 and 65535, got $port");
		}
		if($version !== 4 && $version !== 6){
			throw new \InvalidArgumentException("IP version must be 4 or 6, got $version");
		}
		$this->ip = self::normalizeIp($ip) ?? $ip;
	}

	/**
	 * Returns the canonical text form of an IP address, unbracketed, or null if it is not an IP address.
	 */
	public static function normalizeIp(string $ip) : ?string{
		if(str_starts_with($ip, "[") && str_ends_with($ip, "]")){
			$ip = substr($ip, 1, -1);
		}
		$packed = @inet_pton($ip);
		if($packed === false){
			return null;
		}
		$normalized = inet_ntop($packed);
		return $normalized === false ? null : $normalized;
	}

	/**
	 * Parses "ip:port", "[ip]:port" or an unbracketed IPv6 "ip:port"
	 */
	public static function parse(string $address) : ?self{
		$separator = strrpos($address, ":");
		if($separator === false){
			return null;
		}

		$ip = substr($address, 0, $separator);
		$port = substr($address, $separator + 1);
		if(str_starts_with($ip, "[") && str_ends_with($ip, "]")){
			$ip = substr($ip, 1, -1);
		}
		if(!ctype_digit($port) || (int) $port > 65535 || filter_var($ip, FILTER_VALIDATE_IP) === false){
			return null;
		}

		return new self($ip, (int) $port, filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 6 : 4);
	}

	public function equals(self $other) : bool{
		return $this->ip === $other->ip && $this->port === $other->port && $this->version === $other->version;
	}

	public function toString() : string{
		return ($this->version === 6 ? "[$this->ip]" : $this->ip) . ":" . $this->port;
	}

	public function __toString() : string{
		return $this->toString();
	}
}
