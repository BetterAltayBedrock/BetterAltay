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

final class TransferPlayerUIOptions{

	public function __construct(
		private ?string $titleText,
		private ?string $bodyText,
		private ?bool $titleVisible,
		private ?bool $bodyVisible,
		private ?string $cancelButtonText,
		private ?bool $cancelButtonVisible,
		private ?float $cancelButtonDelaySeconds
	){}

	public function getTitleText() : ?string{ return $this->titleText; }

	public function getBodyText() : ?string{ return $this->bodyText; }

	public function getTitleVisible() : ?bool{ return $this->titleVisible; }

	public function getBodyVisible() : ?bool{ return $this->bodyVisible; }

	public function getCancelButtonText() : ?string{ return $this->cancelButtonText; }

	public function getCancelButtonVisible() : ?bool{ return $this->cancelButtonVisible; }

	public function getCancelButtonDelaySeconds() : ?float{ return $this->cancelButtonDelaySeconds; }

	public static function read(NetworkBinaryStream $in) : self{
		return new self(
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getBool()),
			$in->readOptional(fn() => $in->getBool()),
			$in->readOptional(fn() => $in->getString()),
			$in->readOptional(fn() => $in->getBool()),
			$in->readOptional(fn() => $in->getLFloat())
		);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->writeOptional($this->titleText, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->bodyText, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->titleVisible, fn(bool $value) => $out->putBool($value));
		$out->writeOptional($this->bodyVisible, fn(bool $value) => $out->putBool($value));
		$out->writeOptional($this->cancelButtonText, fn(string $value) => $out->putString($value));
		$out->writeOptional($this->cancelButtonVisible, fn(bool $value) => $out->putBool($value));
		$out->writeOptional($this->cancelButtonDelaySeconds, fn(float $value) => $out->putLFloat($value));
	}
}
