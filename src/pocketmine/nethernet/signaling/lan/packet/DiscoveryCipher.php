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

namespace pocketmine\nethernet\signaling\lan\packet;

use function hash;
use function hash_equals;
use function hash_hmac;
use function openssl_decrypt;
use function openssl_encrypt;
use function pack;
use function strlen;
use function substr;
use const OPENSSL_RAW_DATA;

final class DiscoveryCipher{

	/**
	 * Application ID used to derive the discovery cipher key.
	 */
	public const APPLICATION_ID = 0xdeadbeef;

	public const CHECKSUM_SIZE = 32;
	private const BLOCK_SIZE = 16;

	private function __construct(){}

	public static function key() : string{
		static $key = null;

		return $key ??= hash("sha256", pack("P", self::APPLICATION_ID), true);
	}

	public static function seal(string $payload) : string{
		$key = self::key();

		$ciphertext = openssl_encrypt($payload, "aes-256-ecb", $key, OPENSSL_RAW_DATA, "");
		if($ciphertext === false){
			throw new DiscoveryException("Could not encrypt the datagram");
		}

		return hash_hmac("sha256", $payload, $key, true) . $ciphertext;
	}

	/**
	 * @throws DiscoveryException
	 */
	public static function open(string $frame) : string{
		if(strlen($frame) < self::CHECKSUM_SIZE + self::BLOCK_SIZE){
			throw new DiscoveryException("Datagram is shorter than a checksum and one block");
		}

		$ciphertext = substr($frame, self::CHECKSUM_SIZE);
		if(strlen($ciphertext) % self::BLOCK_SIZE !== 0){
			throw new DiscoveryException("Ciphertext length must be a multiple of the block size (16 bytes)");
		}

		$key = self::key();
		$payload = openssl_decrypt($ciphertext, "aes-256-ecb", $key, OPENSSL_RAW_DATA, "");
		if($payload === false){
			throw new DiscoveryException("Could not decrypt the datagram");
		}

		if(!hash_equals(hash_hmac("sha256", $payload, $key, true), substr($frame, 0, self::CHECKSUM_SIZE))){
			throw new DiscoveryException("Datagram checksum does not match");
		}

		return $payload;
	}
}
