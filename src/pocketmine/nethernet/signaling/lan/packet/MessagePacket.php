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
use function strlen;

final class MessagePacket extends Packet{

	private const MAX_DATA_LENGTH = 65535;

	public function __construct(
		int $senderId,
		public readonly int $recipientId,
		public readonly string $data
	){
		parent::__construct($senderId);
	}

	public function getType() : PacketType{ return PacketType::MESSAGE; }

	/**
	 * @throws DataDecodeException
	 * @throws DiscoveryException
	 */
	public static function decodeBody(int $senderId, ByteBufferReader $in) : self{
		$recipientId = LE::readUnsignedLong($in);

		$length = LE::readUnsignedInt($in);
		if($length > self::MAX_DATA_LENGTH){
			throw new DiscoveryException("Message of $length bytes is larger than a datagram can carry");
		}

		// Vanilla packets can carry more than the length they declare; all of it is the message
		return new self($senderId, $recipientId, $in->readByteArray($length) . $in->readByteArray($in->getUnreadLength()));
	}

	public function encodeBody(ByteBufferWriter $out) : void{
		LE::writeUnsignedLong($out, $this->recipientId);
		LE::writeUnsignedInt($out, strlen($this->data));
		$out->writeByteArray($this->data);
	}
}
