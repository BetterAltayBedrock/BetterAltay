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

namespace pocketmine\network\mcpe;

use pocketmine\snooze\SleeperNotifier;

/**
 * Wakes the server's tick sleeper on a fixed short interval so the NetherNet transport gets ticked many times per
 * game tick. Unlike RakLib, NetherNet has no thread of its own: its sessions only deliver packets when they are
 * ticked, so without this the transport would be limited to the 20 TPS game tick and add latency to every packet.
 */
class NetherNetTickerThread extends \Thread{

	/** @var SleeperNotifier */
	private $notifier;
	/** @var int */
	private $intervalMs;
	/** @var bool */
	protected $shutdown = false;

	public function __construct(SleeperNotifier $notifier, int $intervalMs){
		$this->notifier = $notifier;
		$this->intervalMs = $intervalMs;
	}

	public function run() : void{
		while($this->shutdown !== true){
			usleep($this->intervalMs * 1000);
			$this->notifier->wakeupSleeper();
		}
	}

	public function shutdown() : void{
		$this->shutdown = true;
		if($this->isStarted()){
			$this->join();
		}
	}
}
