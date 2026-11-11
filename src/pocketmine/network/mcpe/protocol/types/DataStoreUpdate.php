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

/**
 * @see ServerboundDataStorePacket&ClientboundDataStorePacket
 */
final class DataStoreUpdate extends DataStore{
	public const ID = DataStoreType::UPDATE;

	public function __construct(
		private string $name,
		private DataStoreValue $data,
		private int $updateCount,
	){
	}

	public function getTypeId() : int{
		return self::ID;
	}

	public function getName() : string{ return $this->name; }

	public function getData() : DataStoreValue{ return $this->data; }

	public function getUpdateCount() : int{ return $this->updateCount; }

	public static function read(NetworkBinaryStream $in) : self{
		$name = $in->getString();
		$data = DataStoreValue::readWithType($in);
		$updateCount = $in->getLInt();

		return new self(
			$name,
			$data,
			$updateCount,
		);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putString($this->name);
		$this->data->writeWithType($out);
		$out->putLInt($this->updateCount);
	}
}