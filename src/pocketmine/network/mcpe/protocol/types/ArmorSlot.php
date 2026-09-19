<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class ArmorSlot{

	private function __construct(){
		//NOOP
	}

	public const HEAD = 0;
	public const TORSO = 1;
	public const LEGS = 2;
	public const FEET = 3;
	public const BODY = 4;

}
