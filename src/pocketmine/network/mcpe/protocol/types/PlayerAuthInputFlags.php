<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

/**
 * These flags are used in PlayerAuthInputPacket's inputFlags field.
 * The flags should be written as
 * `flags |= (1 << flag)`
 * and read as
 * `(flags & (1 << flag)) !== 0`
 *
 * @see PlayerAuthInputPacket
 */
final class PlayerAuthInputFlags{
	public const ASCEND = 0;
	public const DESCEND = 1;
	public const NORTH_JUMP = 2;
	public const JUMP_DOWN = 3;
	public const SPRINT_DOWN = 4;
	public const CHANGE_HEIGHT = 5;
	public const JUMPING = 6;
	public const AUTO_JUMPING_IN_WATER = 7;
	public const SNEAKING = 8;
	public const SNEAK_DOWN = 9;
	public const UP = 10;
	public const DOWN = 11;
	public const LEFT = 12;
	public const RIGHT = 13;
	public const UP_LEFT = 14;
	public const UP_RIGHT = 15;
	public const WANT_UP = 16;
	public const WANT_DOWN = 17;
	public const WANT_DOWN_SLOW = 18;
	public const WANT_UP_SLOW = 19;
	public const SPRINTING = 20;
	public const ASCEND_BLOCK = 21;
	public const DESCEND_BLOCK = 22;
	public const SNEAK_TOGGLE_DOWN = 23;
	public const PERSIST_SNEAK = 24;
	public const START_SPRINTING = 25;
	public const STOP_SPRINTING = 26;
	public const START_SNEAKING = 27;
	public const STOP_SNEAKING = 28;
	public const START_SWIMMING = 29;
	public const STOP_SWIMMING = 30;
	public const START_JUMPING = 31;
	public const START_GLIDING = 32;
	public const STOP_GLIDING = 33;
	public const PERFORM_ITEM_INTERACTION = 34;
	public const PERFORM_BLOCK_ACTIONS = 35;
	public const PERFORM_ITEM_STACK_REQUEST = 36;
	public const HANDLE_TELEPORT = 37;
	public const EMOTING = 38;
	public const MISSED_SWING = 39;
	public const START_CRAWLING = 40;
	public const STOP_CRAWLING = 41;
	public const START_FLYING = 42;
	public const STOP_FLYING = 43;
	public const ACK_ACTOR_DATA = 44;
	public const IN_CLIENT_PREDICTED_VEHICLE = 45;
	public const PADDLING_LEFT = 46;
	public const PADDLING_RIGHT = 47;
	public const BLOCK_BREAKING_DELAY_ENABLED = 48;
	public const HORIZONTAL_COLLISION = 49;
	public const VERTICAL_COLLISION = 50;
	public const DOWN_LEFT = 51;
	public const DOWN_RIGHT = 52;
	public const START_USING_ITEM = 53;
	public const IS_CAMERA_RELATIVE_MOVEMENT_ENABLED = 54;
	public const IS_ROT_CONTROLLED_BY_MOVE_DIRECTION = 55;
	public const START_SPIN_ATTACK = 56;
	public const STOP_SPIN_ATTACK = 57;
	public const IS_HOTBAR_ONLY_TOUCH = 58;
	public const JUMP_RELEASED_RAW = 59;
	public const JUMP_PRESSED_RAW = 60;
	public const JUMP_CURRENT_RAW = 61;
	public const SNEAK_RELEASED_RAW = 62;
	public const SNEAK_PRESSED_RAW = 63;
	public const SNEAK_CURRENT_RAW = 64;
	public const INTERNAL_UPDATE = 65;

	public const NUMBER_OF_FLAGS = 66;
}
