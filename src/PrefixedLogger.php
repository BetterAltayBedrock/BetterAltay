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
 * Stand-in for pocketmine/log's PrefixedLogger in the root namespace. The vendored NetherNet library expects this
 * class to exist (pocketmine/log 0.4 provides it there, but this server still uses 0.2, which only has the Logger
 * interface). Every message is forwarded to the wrapped logger with a fixed prefix.
 */
class PrefixedLogger implements \Logger{

	/** @var \Logger */
	private $logger;
	/** @var string */
	private $prefix;

	public function __construct(\Logger $logger, string $prefix){
		$this->logger = $logger;
		$this->prefix = $prefix;
	}

	private function format($message) : string{
		return "[" . $this->prefix . "] " . $message;
	}

	public function emergency($message){
		$this->logger->emergency($this->format($message));
	}

	public function alert($message){
		$this->logger->alert($this->format($message));
	}

	public function critical($message){
		$this->logger->critical($this->format($message));
	}

	public function error($message){
		$this->logger->error($this->format($message));
	}

	public function warning($message){
		$this->logger->warning($this->format($message));
	}

	public function notice($message){
		$this->logger->notice($this->format($message));
	}

	public function info($message){
		$this->logger->info($this->format($message));
	}

	public function debug($message){
		$this->logger->debug($this->format($message));
	}

	public function log($level, $message){
		$this->logger->log($level, $this->format($message));
	}

	public function logException(\Throwable $e, $trace = null){
		$this->logger->logException($e, $trace);
	}
}
