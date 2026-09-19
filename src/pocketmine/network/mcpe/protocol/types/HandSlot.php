<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class HandSlot{

	private function __construct(){
		//NOOP
	}

	public const MAIN_HAND = 0;
	public const OFF_HAND = 1;

}
