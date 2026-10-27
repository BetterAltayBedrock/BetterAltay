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

namespace pocketmine\nethernet\utils;

use pocketmine\nethernet\NetherNetException;
use function socket_strerror;

class SocketException extends NetherNetException{

	public static function fromErrno(string $what, int $errno) : self{
		return new self("$what: " . socket_strerror($errno), $errno);
	}
}
