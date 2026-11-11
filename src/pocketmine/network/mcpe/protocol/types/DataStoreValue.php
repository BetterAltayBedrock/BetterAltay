<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;
use UnexpectedValueException;

abstract class DataStoreValue{

	abstract public function getTypeId() : int;

	abstract public function write(NetworkBinaryStream $out) : void;

	public static function readWithType(NetworkBinaryStream $in) : self{
		return match($in->getUnsignedVarInt()){
			DataStoreValueType::DOUBLE => DoubleDataStoreValue::read($in),
			DataStoreValueType::BOOL => BoolDataStoreValue::read($in),
			DataStoreValueType::STRING => StringDataStoreValue::read($in),
			default => throw new UnexpectedValueException("Unknown DataStoreValueType"),
		};
	}

	public function writeWithType(NetworkBinaryStream $out) : void{
		$out->putUnsignedVarInt($this->getTypeId());
		$this->write($out);
	}
}
