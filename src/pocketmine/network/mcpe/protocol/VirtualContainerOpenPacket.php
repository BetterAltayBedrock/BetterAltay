<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use function count;

final class VirtualContainerOpenPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::VIRTUAL_CONTAINER_OPEN_PACKET;

	public int $containerId;
	public int $sessionId;
	public string $title;
	/** @var ItemStackWrapper[] */
	public array $slots = [];

	/**
	 * @param ItemStackWrapper[] $slots
	 */
	public static function create(int $containerId, int $sessionId, string $title, array $slots) : self{
		$result = new self;
		$result->containerId = $containerId;
		$result->sessionId = $sessionId;
		$result->title = $title;
		$result->slots = $slots;
		return $result;
	}

	protected function decodePayload() : void{
		$this->containerId = $this->getByte();
		$this->sessionId = $this->getUnsignedVarLong();
		$this->title = $this->getString();
		$slotsCount = $this->getUnsignedVarInt();
		$this->slots = [];
		for($i = 0; $i < $slotsCount; ++$i){
			$this->slots[] = ItemStackWrapper::read($this, true);
		}
	}

	protected function encodePayload() : void{
		$this->putByte($this->containerId);
		$this->putUnsignedVarLong($this->sessionId);
		$this->putString($this->title);
		$this->putUnsignedVarInt(count($this->slots));
		foreach($this->slots as $slot){
			$slot->write($this, true);
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleVirtualContainerOpen($this);
	}
}
