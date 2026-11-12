<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class VirtualContainerCloseCause{

	private function __construct(){
		//NOOP
	}

	public const UNKNOWN = 0;
	public const USER_CLOSED = 1;
	public const SERVER_REQUESTED = 2;
	public const USER_BUSY = 3;
	public const CLIENT_NOT_READY = 4;
	public const INVALID_CONTENTS = 5;
	public const UI_CREATION_FAILED = 6;
	public const UI_CANCELLED = 7;

}
