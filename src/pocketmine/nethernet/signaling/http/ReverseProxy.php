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

namespace pocketmine\nethernet\signaling\http;

use function array_reverse;
use function count;
use function ctype_digit;
use function explode;
use function inet_ntop;
use function inet_pton;
use function ord;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strcasecmp;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Resolves the client address from the single header the proxy writes. Any other header is client-controlled.
 */
final class ReverseProxy{

	public const HEADER_FORWARDED_FOR = "X-Forwarded-For";
	public const HEADER_REAL_IP = "X-Real-IP";
	public const HEADER_CF_CONNECTING_IP = "CF-Connecting-IP";

	/** RFC 7239 header, rejected because its `for=...;proto=...` syntax is not parsed. */
	private const HEADER_FORWARDED = "Forwarded";

	private const IPV4_MAPPED_PREFIX = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";

	/** @phpstan-var list<array{string, int}> packed address and prefix length */
	private array $networks = [];

	/** Whether the header is a list of hops rather than a single address. */
	private readonly bool $list;

	/**
	 * @param string   $header   Header the proxy writes the client address to.
	 * @param string[] $networks Addresses or CIDR blocks of the proxies to trust.
	 * @phpstan-param non-empty-list<string> $networks
	 *
	 * @throws \InvalidArgumentException if the header is not usable or a network cannot be parsed
	 */
	public function __construct(
		private readonly string $header,
		array $networks
	){
		if(preg_match(HttpRequest::TOKEN_PATTERN, $header) !== 1){
			throw new \InvalidArgumentException("\"$header\" is not a valid header name");
		}
		if(strcasecmp($header, self::HEADER_FORWARDED) === 0){
			throw new \InvalidArgumentException("The " . self::HEADER_FORWARDED . " header is not supported");
		}
		if(count($networks) === 0){
			throw new \InvalidArgumentException("At least one trusted proxy network must be specified");
		}
		foreach($networks as $network){
			$this->networks[] = self::parseNetwork($network);
		}
		$this->list = strcasecmp($header, self::HEADER_FORWARDED_FOR) === 0;
	}

	public function getHeader() : string{ return $this->header; }

	public function trusts(string $address) : bool{
		$packed = @inet_pton($address);
		if($packed === false){
			return false;
		}
		foreach($this->networks as [$network, $prefix]){
			if(self::inNetwork($packed, $network, $prefix)){
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the client address, or null if the header is missing or unusable.
	 *
	 * A list of hops is read right to left up to the first untrusted address, or the leftmost if all are trusted.
	 */
	public function clientAddress(HttpRequest $request) : ?string{
		$value = $request->getHeader($this->header);
		if($value === null){
			return null;
		}
		if(!$this->list){
			return self::normalize($value);
		}

		$address = null;
		foreach(array_reverse(explode(",", $value)) as $entry){
			$address = self::normalize($entry);
			//entries further left are client-controlled
			if($address === null || !$this->trusts($address)){
				break;
			}
		}

		return $address;
	}

	/**
	 * Returns the canonical IP without brackets or port, unmapping IPv4-mapped addresses, or null if invalid.
	 */
	private static function normalize(string $entry) : ?string{
		$entry = trim($entry);
		if(str_starts_with($entry, "[")){
			$close = strpos($entry, "]");
			if($close === false){
				return null;
			}
			$entry = substr($entry, 1, $close - 1);
		}elseif(preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $entry, $matches) === 1){
			$entry = $matches[1];
		}

		$packed = @inet_pton($entry);
		if($packed === false){
			return null;
		}
		if(strlen($packed) === 16 && str_starts_with($packed, self::IPV4_MAPPED_PREFIX)){
			$packed = substr($packed, strlen(self::IPV4_MAPPED_PREFIX));
		}
		$normalized = inet_ntop($packed);

		return $normalized === false ? null : $normalized;
	}

	/**
	 * @phpstan-return array{string, int}
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function parseNetwork(string $network) : array{
		$address = $network;
		$prefix = null;
		if(str_contains($network, "/")){
			[$address, $prefixString] = explode("/", $network, 2);
			if(!ctype_digit($prefixString)){
				throw new \InvalidArgumentException("Network \"$network\" has a non-numeric CIDR prefix length");
			}
			$prefix = (int) $prefixString;
		}

		$packed = @inet_pton($address);
		if($packed === false){
			throw new \InvalidArgumentException("Network \"$network\" is not an IP address or CIDR block");
		}

		$bits = strlen($packed) * 8;
		$prefix ??= $bits;
		if($prefix > $bits){
			throw new \InvalidArgumentException("Network \"$network\" prefix length must be between 0 and $bits");
		}

		return [$packed, $prefix];
	}

	private static function inNetwork(string $packed, string $network, int $prefix) : bool{
		if(strlen($packed) !== strlen($network)){
			return false;
		}

		$wholeBytes = $prefix >> 3;
		if(substr($packed, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)){
			return false;
		}

		$remainingBits = $prefix & 7;
		if($remainingBits === 0){
			return true;
		}
		$mask = (0xff << (8 - $remainingBits)) & 0xff;

		return (ord($packed[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
	}
}
