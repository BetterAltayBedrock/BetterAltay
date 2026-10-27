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

namespace pocketmine\nethernet\signaling;

use pocketmine\nethernet\protocol\ErrorCode;
use function explode;
use function ltrim;
use function strcmp;
use function strlen;
use function strspn;
use function substr;

final class Signal{

	private const MAX_CONNECTION_ID = "18446744073709551615";

	public function __construct(
		public readonly SignalType $type,
		public readonly string $connectionId,
		public readonly string $data
	){}

	/**
	 * @throws SignalingException
	 */
	public static function parse(string $message) : self{
		$parts = explode(" ", $message, 3);
		if(!isset($parts[2])){
			throw new SignalingException("Signaling message must have at least three space-separated fields");
		}

		$type = SignalType::tryFrom($parts[0]);
		if($type === null){
			throw new SignalingException("Unknown signaling message type '$parts[0]'");
		}

		return new self($type, self::parseConnectionId($parts[1]), $parts[2]);
	}

	/**
	 * Reads a connection ID the way clients do: the leading decimal digits count and whatever follows them is ignored.
	 *
	 * @throws SignalingException
	 */
	private static function parseConnectionId(string $field) : string{
		$digits = substr($field, 0, strspn($field, "0123456789"));
		if($digits === ""){
			throw new SignalingException("Connection ID is not a number");
		}

		$id = ltrim($digits, "0");
		if(strlen($id) > strlen(self::MAX_CONNECTION_ID) || (strlen($id) === strlen(self::MAX_CONNECTION_ID) && strcmp($id, self::MAX_CONNECTION_ID) > 0)){
			throw new SignalingException("Connection ID does not fit in 64 bits");
		}

		return $id === "" ? "0" : $id;
	}

	public static function error(string $connectionId, ErrorCode $code) : self{
		return new self(SignalType::CONNECT_ERROR, $connectionId, (string) $code->value);
	}

	public function toString() : string{
		return $this->type->value . " " . $this->connectionId . " " . $this->data;
	}
}
