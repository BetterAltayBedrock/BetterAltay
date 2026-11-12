<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class VirtualContainerCloseRequestKind{

	private function __construct(){
		//NOOP
	}

	public const REQUEST = 0;
	public const CLOSED = 1;
	public const CONFIRMED = 2;

}
