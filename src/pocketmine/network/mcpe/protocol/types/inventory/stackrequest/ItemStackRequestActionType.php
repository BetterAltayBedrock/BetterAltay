<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

final class ItemStackRequestActionType{

	private function __construct(){
		//NOOP
	}

	public const TAKE = 0;
	public const PLACE = 1;
	public const SWAP = 2;
	public const DROP = 3;
	public const DESTROY = 4;
	public const CRAFTING_CONSUME_INPUT = 5;
	public const CRAFTING_CREATE_SPECIFIC_RESULT = 6;
	public const LAB_TABLE_COMBINE = 7;
	public const BEACON_PAYMENT = 8;
	public const MINE_BLOCK = 9;
	public const CRAFTING_RECIPE = 10;
	public const CRAFTING_RECIPE_AUTO = 11; //recipe book?
	public const CREATIVE_CREATE = 12;
	public const CRAFTING_RECIPE_OPTIONAL = 13; //anvil/cartography table rename
	public const CRAFTING_GRINDSTONE = 14;
	public const CRAFTING_LOOM = 15;
	public const CRAFTING_DISC_PRESS = 16;
	public const CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING = 17;
	public const CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING = 18; //no idea what this is for

	public const INNER_TYPES = [
		self::TAKE => 0,
		self::PLACE => 1,
		self::SWAP => 2,
		self::DROP => 3,
		self::DESTROY => 4,
		self::CRAFTING_CONSUME_INPUT => 5,
		self::CRAFTING_CREATE_SPECIFIC_RESULT => 6,
		//7 PlaceItemInContainer_DEPRECATED
		//8 TakeItemFromContainer_DEPRECATED
		self::LAB_TABLE_COMBINE => 9,
		self::BEACON_PAYMENT => 10,
		self::MINE_BLOCK => 11,
		self::CRAFTING_RECIPE => 12,
		self::CRAFTING_RECIPE_AUTO => 13, //recipe book?
		self::CREATIVE_CREATE => 14,
		self::CRAFTING_RECIPE_OPTIONAL => 15, //anvil/cartography table rename
		self::CRAFTING_GRINDSTONE => 16,
		self::CRAFTING_LOOM => 17,
		self::CRAFTING_DISC_PRESS => 18,
		self::CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING => 19,
		self::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING => 20, //no idea what this is for
	];
}
