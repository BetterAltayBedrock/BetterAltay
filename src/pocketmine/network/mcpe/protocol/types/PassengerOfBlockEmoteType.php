<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class PassengerOfBlockEmoteType{

    private function __construct(){
        //NOOP
    }

	public const STANDING = 0;
	public const RIDING = 1;
	public const LAYING = 2;

}
