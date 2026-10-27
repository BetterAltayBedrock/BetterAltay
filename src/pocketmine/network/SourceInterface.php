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

/**
 * Network-related classes
 */

namespace pocketmine\network;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\Player;

/**
 * Network interfaces are transport layers which can be used to transmit packets between the server and clients.
 */
interface SourceInterface{

	/**
	 * Performs actions needed to start the interface after it is registered.
	 *
	 * @return void
	 */
	public function start();

	/**
	 * Sends a DataPacket to the interface, returns an unique identifier for the packet if $needACK is true
	 *
	 * @return int|null
	 */
	public function putPacket(Player $player, DataPacket $packet, bool $needACK = false, bool $immediate = true);

	/**
	 * Terminates the connection
	 *
	 * @return void
	 */
	public function close(Player $player, string $reason = "unknown reason");

	/**
	 * Returns whether connections made over this transport are encrypted by the transport itself.
	 *
	 * Transports which do so, and which authenticate the client in the process, do not need the Minecraft-layer
	 * encryption handshake: it would be redundant there, and their clients do not complete it.
	 */
	public function isTransportEncrypted() : bool;

	/**
	 * @return void
	 */
	public function setName(string $name);

	/**
	 * Called every tick to process events on the interface.
	 */
	public function process() : void;

	/**
	 * Gracefully shuts down the network interface.
	 *
	 * @return void
	 */
	public function shutdown();

	/**
	 * @return void
	 * @deprecated
	 * Shuts down the network interface in an emergency situation, such as due to a crash.
	 *
	 */
	public function emergencyShutdown();

}
