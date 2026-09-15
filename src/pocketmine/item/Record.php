<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

class Record extends Item{

	public const SOUND_RECORD_13 = "record.13";
	public const SOUND_RECORD_CAT = "record.cat";
	public const SOUND_RECORD_BLOCKS = "record.blocks";
	public const SOUND_RECORD_CHIRP = "record.chirp";
	public const SOUND_RECORD_FAR = "record.far";
	public const SOUND_RECORD_MALL = "record.mall";
	public const SOUND_RECORD_MELLOHI = "record.mellohi";
	public const SOUND_RECORD_STAL = "record.stal";
	public const SOUND_RECORD_STRAD = "record.strad";
	public const SOUND_RECORD_WARD = "record.ward";
	public const SOUND_RECORD_11 = "record.11";
	public const SOUND_RECORD_WAIT = "record.wait";
	public const SOUND_RECORD_OTHERSIDE = "record.otherside";
	public const SOUND_RECORD_5 = "record.5";
	public const SOUND_RECORD_PIGSTEP = "record.pigstep";
	public const SOUND_RECORD_RELIC = "record.relic";
	public const SOUND_RECORD_CREATOR = "record.creator";
	public const SOUND_RECORD_CREATOR_MUSIC_BOX = "record.creator_music_box";
	public const SOUND_RECORD_PRECIPICE = "record.precipice";
	public const SOUND_RECORD_TEARS = "record.tears";
	public const SOUND_RECORD_LAVA_CHICKEN = "record.lava_chicken";
	public const SOUND_RECORD_BOUNCE = "record.bounce";

	public function __construct(int $id, protected string $soundId){
		parent::__construct($id, 0, "Music Disc");
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getSoundId() : string{
		return $this->soundId;
	}
}