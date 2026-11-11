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
use pocketmine\network\mcpe\protocol\types\CommandOriginData;
use pocketmine\network\mcpe\protocol\types\CommandOutputMessage;
use function count;

class CommandOutputPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::COMMAND_OUTPUT_PACKET;

	public const TYPE_NONE = "none";
	public const TYPE_LAST = "lastoutput";
	public const TYPE_SILENT = "silent";
	public const TYPE_ALL = "alloutput";
	public const TYPE_DATA_SET = "dataset";

	public CommandOriginData $originData;
	public string $outputType;
	public int $successCount;
	/** @var CommandOutputMessage[] */
	public array $messages = [];
	public string $data;

	protected function decodePayload() : void{
		$this->originData = $this->getCommandOriginData();
		$this->outputType = $this->getString();
		$this->successCount = $this->getLInt();

		for($i = 0, $size = $this->getUnsignedVarInt(); $i < $size; ++$i){
			$this->messages[] = $this->getCommandMessage();
		}

		if($this->outputType === self::TYPE_DATA_SET){
			$this->data = $this->getString();
		}
	}

	protected function getCommandMessage() : CommandOutputMessage{
		$message = new CommandOutputMessage();

		$message->messageId = $this->getString();
		$message->successful = $this->getBool();

		for($i = 0, $size = $this->getUnsignedVarInt(); $i < $size; ++$i){
			$message->parameters[] = $this->getString();
		}

		$message->rawMessage = $this->readOptional(fn() => $this->getString());

		return $message;
	}

	protected function encodePayload() : void{
		$this->putCommandOriginData($this->originData);
		$this->putString($this->outputType);
		$this->putLInt($this->successCount);

		$this->putUnsignedVarInt(count($this->messages));
		foreach($this->messages as $message){
			$this->putCommandMessage($message);
		}

		if($this->outputType === self::TYPE_DATA_SET){
			$this->putString($this->data);
		}
	}

	/**
	 * @return void
	 */
	protected function putCommandMessage(CommandOutputMessage $message) : void{
		$this->putString($message->messageId);
		$this->putBool($message->successful);

		$this->putUnsignedVarInt(count($message->parameters));
		foreach($message->parameters as $parameter){
			$this->putString($parameter);
		}

		$this->writeOptional($message->rawMessage, fn(string $rawMessage) => $this->putString($rawMessage));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleCommandOutput($this);
	}
}
