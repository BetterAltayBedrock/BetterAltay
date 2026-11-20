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

use UnexpectedValueException;
use function count;

/**
 * Trait for string-backed enums serialized in packets, with ordinal-based case resolution.
 * Provides helpers to read, validate and properly bail on invalid values.
 */
trait PacketOrdinalEnumTrait{

	/**
	 * @throws UnexpectedValueException
	 */
	public static function fromPacket(string $value) : self{
		$enum = self::tryFrom($value);
		if($enum === null){
			throw new UnexpectedValueException("Invalid raw case '$value' for " . static::class);
		}

		return $enum;
	}

	public function toOrdinal() : int{
		/** @var array<string, int> $ordinals */
		static $ordinals = [];

		if(count($ordinals) === 0){
			foreach(self::cases() as $i => $case){
				$ordinals[$case->name] = $i;
			}
		}

		return $ordinals[$this->name];
	}

	/**
	 * @throws UnexpectedValueException
	 */
	public static function fromOrdinal(int $ordinal) : static{
		/**
		 * @var static[] $cases
		 * @phpstan-var list<static> $cases
		 */
		static $cases = [];

		if(count($cases) === 0){
			$cases = self::cases();
		}

		if(!isset($cases[$ordinal])){
			throw new UnexpectedValueException("Invalid ordinal value $ordinal for " . static::class);
		}

		return $cases[$ordinal];
	}
}
