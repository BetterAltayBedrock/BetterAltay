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

use InvalidArgumentException;
use pocketmine\network\mcpe\NetworkSession;
use UnexpectedValueException;
use function count;

class TextPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::TEXT_PACKET;

	public const TYPE_RAW = 0;
	public const TYPE_CHAT = 1;
	public const TYPE_TRANSLATION = 2;
	public const TYPE_POPUP = 3;
	public const TYPE_JUKEBOX_POPUP = 4;
	public const TYPE_TIP = 5;
	public const TYPE_SYSTEM = 6;
	public const TYPE_WHISPER = 7;
	public const TYPE_ANNOUNCEMENT = 8;
	public const TYPE_JSON_WHISPER = 9;
	public const TYPE_JSON = 10;
	public const TYPE_JSON_ANNOUNCEMENT = 11;

	private const ONEOF_MESSAGE_ONLY = 0;
	private const ONEOF_AUTHOR_AND_MESSAGE = 1;
	private const ONEOF_MESSAGE_AND_PARAMS = 2;

	public int $type; //TextPacket::TYPE_*
	public bool $needsTranslation = false;
	public string $sourceName;
	public string $message;
	/** @var string[] */
	public array $parameters = [];
	public bool $queue = false;
	public string $xboxUserId = "";
	public string $platformChatId = "";
	public ?string $filteredMessage = null;

	protected function decodePayload() : void{
		$this->needsTranslation = $this->getBool();
		$oneOfType = $this->getByte();

		$this->type = $this->getUnsignedVarInt();

		switch($oneOfType){
			case self::ONEOF_MESSAGE_ONLY:
				$this->message = $this->getString();
				break;

			case self::ONEOF_AUTHOR_AND_MESSAGE:
				$this->sourceName = $this->getString();
				$this->message = $this->getString();
				break;

			case self::ONEOF_MESSAGE_AND_PARAMS:
				$this->message = $this->getString();
				$count = $this->getUnsignedVarInt();
				for($i = 0; $i < $count; ++$i){
					$this->parameters[] = $this->getString();
				}
				$this->queue = $this->getBool();
				break;
		}

		$this->xboxUserId = $this->getString();
		$this->platformChatId = $this->getString();
		$this->filteredMessage = $this->readOptional(fn() => $this->getString());
	}

	protected function encodePayload() : void{
		$this->putBool($this->needsTranslation);

		$oneOfType = $this->getOneOfType($this->type);

		$this->putByte($oneOfType);

		$this->putUnsignedVarInt($this->type);

		$message = $this->message === "" ? " " : $this->message;

		switch($oneOfType){
			case self::ONEOF_MESSAGE_ONLY:
				$this->putString($message);
				break;

			case self::ONEOF_AUTHOR_AND_MESSAGE:
				$this->putString($this->sourceName);
				$this->putString($message);
				break;

			case self::ONEOF_MESSAGE_AND_PARAMS:
				$this->putString($message);
				$this->putUnsignedVarInt(count($this->parameters));
				foreach($this->parameters as $p){
					$this->putString($p);
				}
				$this->putBool($this->queue);
				break;
		}

		$this->putString($this->xboxUserId);
		$this->putString($this->platformChatId);
		$this->writeOptional($this->filteredMessage, fn(string $filteredMessage) => $this->putString($filteredMessage));
	}

	protected function getOneOfType(int $textType) : int{
		return match ($textType) {
			self::TYPE_CHAT,
			self::TYPE_WHISPER,
			self::TYPE_ANNOUNCEMENT => self::ONEOF_AUTHOR_AND_MESSAGE,
			self::TYPE_TRANSLATION,
			self::TYPE_POPUP,
			self::TYPE_JUKEBOX_POPUP => self::ONEOF_MESSAGE_AND_PARAMS,
			self::TYPE_RAW,
			self::TYPE_TIP,
			self::TYPE_SYSTEM,
			self::TYPE_JSON,
			self::TYPE_JSON_WHISPER,
			self::TYPE_JSON_ANNOUNCEMENT => self::ONEOF_MESSAGE_ONLY,

			default => throw new InvalidArgumentException("Unsupported TextType " . $textType),
		};
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleText($this);
	}
}
