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

namespace pocketmine\nethernet\info;

/**
 * What a host tells clients about itself before they join.
 */
final class ServerInfo{

	/** Version of the layout this information is advertised in. */
	public const DATA_VERSION = 7;

	public const TRANSPORT_LAYER_NETHERNET = 2;

	public const CONNECTION_TYPE_NETHERNET = 4;

	/**
	 * @param string $nonce Optional verification nonce echoed back in client Login ClientData.
	 */
	public function __construct(
		public readonly string $serverName,
		public readonly int $protocol,
		public readonly string $gameVersion,
		public readonly string $levelName,
		public readonly GameType $gameType = GameType::SURVIVAL,
		public readonly int $playerCount = 0,
		public readonly int $maxPlayerCount = 0,
		public readonly bool $editorWorld = false,
		public readonly bool $hardcore = false,
		public readonly bool $acceptsOnlineAuth = true,
		public readonly bool $acceptsSelfSignedAuth = true,
		public readonly string $nonce = ""
	){}
}
