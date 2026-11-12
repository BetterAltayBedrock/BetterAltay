<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class SCTPRecoveryProfile{

	private function __construct(){
		//NOOP
	}

	public const DEFAULT = 0;
	public const FAST = 1;
	public const BALANCED = 2;

}
