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

namespace pocketmine\nethernet\negotiation;

use pocketmine\nethernet\NetherNetException;
use pocketmine\nethernet\protocol\ErrorCode;

class NegotiationException extends NetherNetException{

	public function __construct(
		string $message,
		private readonly ErrorCode $errorCode = ErrorCode::GENERIC_FAILURE,
		?\Throwable $previous = null
	){
		parent::__construct($message, 0, $previous);
	}

	public function getErrorCode() : ErrorCode{ return $this->errorCode; }
}
