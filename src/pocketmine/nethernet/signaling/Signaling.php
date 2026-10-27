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

interface Signaling{

	public function describe() : string;

	/**
	 * @throws SignalingException
	 * @throws \LogicException if already started or shut down
	 */
	public function start(SignalingContext $context) : void;

	public function tick(float $now) : void;

	/**
	 * Must be safe to call even if {@link Signaling::start()} failed or was never called.
	 */
	public function shutdown() : void;
}
