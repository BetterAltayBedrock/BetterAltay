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

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use function bin2hex;
use function ctype_xdigit;
use function hex2bin;
use function strlen;

final class ResponsePacket extends Packet{

	private const MAX_ENCODED_LENGTH = 65535;

	public function __construct(
		int $senderId,
		public readonly string $applicationData
	){
		parent::__construct($senderId);
	}

	public function getType() : PacketType{ return PacketType::RESPONSE; }

	/**
	 * @throws DataDecodeException
	 * @throws DiscoveryException
	 */
	public static function decodeBody(int $senderId, ByteBufferReader $in) : self{
		$length = LE::readUnsignedInt($in);
		if($length > self::MAX_ENCODED_LENGTH){
			throw new DiscoveryException("Invalid data length $length (max " . self::MAX_ENCODED_LENGTH . ")");
		}

		$hex = $in->readByteArray($length);
		/* hex2bin() raises a warning for input it cannot decode, so that is ruled out before it is asked. */
		$decoded = $length % 2 === 0 && ($hex === "" || ctype_xdigit($hex)) ? hex2bin($hex) : false;
		if($decoded === false){
			throw new DiscoveryException("Invalid hex data");
		}

		return new self($senderId, $decoded);
	}

	public function encodeBody(ByteBufferWriter $out) : void{
		$hex = bin2hex($this->applicationData);
		LE::writeUnsignedInt($out, strlen($hex));
		$out->writeByteArray($hex);
	}
}
