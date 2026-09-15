<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

final class SoundDataType{

	private function __construct(){
		//NOOP
	}

	public const STOP = 0;
	public const SET_VOLUME = 1;
	public const SET_PITCH = 2;
	public const FADE = 3;
	public const SEEK_TO = 4;
	public const PAUSE = 5;
	public const RESUME = 6;
}