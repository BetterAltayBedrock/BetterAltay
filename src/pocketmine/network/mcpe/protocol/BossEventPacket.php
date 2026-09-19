<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;

class BossEventPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::BOSS_EVENT_PACKET;

	/* S2C: Shows the boss-bar to the player. */
	public const TYPE_SHOW = 0;
	/* C2S: Registers a player to a boss fight. */
	public const TYPE_REGISTER_PLAYER = 1;
	/* S2C: Removes the boss-bar from the client. */
	public const TYPE_HIDE = 2;
	/* C2S: Unregisters a player from a boss fight. */
	public const TYPE_UNREGISTER_PLAYER = 3;
	/* S2C: Sets the bar percentage. */
	public const TYPE_HEALTH_PERCENT = 4;
	/* S2C: Sets title of the bar. */
	public const TYPE_TITLE = 5;
	/* S2C: Not sure on this. TODO: check this */
	public const TYPE_UPDATE_PROPERTIES = 6;
	/* S2C: Not implemented :( Intended to alter bar appearance, but these currently produce no effect on client-side whatsoever. */
	public const TYPE_TEXTURE = 7;
	/* C2S: Client asking the server to resend all boss data. */
	public const TYPE_QUERY = 8;

	public int $bossEid;
	public int $eventType;
	public float $healthPercent;
	public string $title;
	public string $filteredTitle = "";
	public int $color;
	public int $overlay;

	protected function decodePayload() : void{
		$this->bossEid = $this->getEntityUniqueId();
		$this->eventType = $this->getByte();
		$this->title = $this->getString();
		$this->filteredTitle = $this->getString();
		$this->healthPercent = $this->getLFloat();
		$this->color = $this->getByte();
		$this->overlay = $this->getByte();
	}

	protected function encodePayload() : void{
		$this->putEntityUniqueId($this->bossEid);
		$this->putByte($this->eventType);
		$this->putString($this->title);
		$this->putString($this->filteredTitle);
		$this->putLFloat($this->healthPercent);
		$this->putByte($this->color);
		$this->putByte($this->overlay);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleBossEvent($this);
	}
}
