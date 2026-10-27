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

namespace pocketmine\nethernet\signaling\http;

use pocketmine\nethernet\signaling\SignalingException;

/**
 * A request that cannot be served, carrying the status to answer it with.
 */
class HttpException extends SignalingException{

	public function __construct(
		private readonly HttpStatus $status,
		string $message
	){
		parent::__construct($message);
	}

	public function getStatus() : HttpStatus{ return $this->status; }
}
