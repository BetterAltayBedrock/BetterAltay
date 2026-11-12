<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\VirtualContainerCloseCause;
use pocketmine\network\mcpe\protocol\types\VirtualContainerCloseRequestKind;

final class VirtualContainerClosePacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::VIRTUAL_CONTAINER_CLOSE_PACKET;

	public int $sessionId;
	/** @see VirtualContainerCloseRequestKind */
	public int $kind = VirtualContainerCloseRequestKind::REQUEST;
	/** @see VirtualContainerCloseCause */
	public int $cause = VirtualContainerCloseCause::UNKNOWN;

	public static function create(int $sessionId, int $kind = VirtualContainerCloseRequestKind::REQUEST, int $cause = VirtualContainerCloseCause::UNKNOWN) : self{
		$result = new self;
		$result->sessionId = $sessionId;
		$result->kind = $kind;
		$result->cause = $cause;
		return $result;
	}

	protected function decodePayload() : void{
		$this->sessionId = $this->getUnsignedVarLong();
		$this->kind = $this->getByte();
		$this->cause = $this->getByte();
	}

	protected function encodePayload() : void{
		$this->putUnsignedVarLong($this->sessionId);
		$this->putByte($this->kind);
		$this->putByte($this->cause);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleVirtualContainerClose($this);
	}
}
