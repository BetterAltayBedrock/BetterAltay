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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pocketmine\network\mcpe\NetworkBinaryStream;
use UnexpectedValueException;
use function count;

final class ItemStackRequest{


	/**
	 * @param ItemStackRequestAction[] $actions
	 * @param string[]                 $filterStrings
	 *
	 * @phpstan-param list<string>     $filterStrings
	 */
	public function __construct(
		private int $requestId,
		private array $actions,
		private array $filterStrings,
		private int $filterStringCause
	){}

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackRequestAction[] */
	public function getActions() : array{ return $this->actions; }

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getFilterStrings() : array{ return $this->filterStrings; }

	public function getFilterStringCause() : int{ return $this->filterStringCause; }

	private static function readAction(NetworkBinaryStream $in, int $outerTypeId) : ItemStackRequestAction{
		$innerTypeId = $in->getByte();
		$expectedInner = ItemStackRequestActionType::INNER_TYPES[$outerTypeId] ?? null;
		if($expectedInner === null || $expectedInner !== $innerTypeId){
			throw new UnexpectedValueException("Unhandled item stack request action type $outerTypeId (inner $innerTypeId)");
		}
		switch($innerTypeId){
			case ItemStackRequestActionType::TAKE:
				return TakeStackRequestAction::read($in);
			case ItemStackRequestActionType::PLACE:
				return PlaceStackRequestAction::read($in);
			case ItemStackRequestActionType::SWAP:
				return SwapStackRequestAction::read($in);
			case ItemStackRequestActionType::DROP:
				return DropStackRequestAction::read($in);
			case ItemStackRequestActionType::DESTROY:
				return DestroyStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_CONSUME_INPUT:
				return CraftingConsumeInputStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_CREATE_SPECIFIC_RESULT:
				return CraftingCreateSpecificResultStackRequestAction::read($in);
			case ItemStackRequestActionType::LAB_TABLE_COMBINE:
				return LabTableCombineStackRequestAction::read($in);
			case ItemStackRequestActionType::BEACON_PAYMENT:
				return BeaconPaymentStackRequestAction::read($in);
			case ItemStackRequestActionType::MINE_BLOCK:
				return MineBlockStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_RECIPE:
				return CraftRecipeStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_RECIPE_AUTO:
				return CraftRecipeAutoStackRequestAction::read($in);
			case ItemStackRequestActionType::CREATIVE_CREATE:
				return CreativeCreateStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_RECIPE_OPTIONAL:
				return CraftRecipeOptionalStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_LOOM:
				return LoomStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING:
				return DeprecatedCraftingNonImplementedStackRequestAction::read($in);
			case ItemStackRequestActionType::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING:
				return DeprecatedCraftingResultsStackRequestAction::read($in);
			default:
				throw new UnexpectedValueException("Unhandled item stack request action type $outerTypeId");
		}
	}

	public static function read(NetworkBinaryStream $in) : self{
		$requestId = $in->readGenericTypeNetworkId();
		$actions = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$outerTypeId = $in->getUnsignedVarInt();
			$actions[] = self::readAction($in, $outerTypeId);
		}
		$filterStrings = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$filterStrings[] = $in->getString();
		}
		$filterStringCause = $in->getLInt();
		return new self($requestId, $actions, $filterStrings, $filterStringCause);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->writeGenericTypeNetworkId($this->requestId);
		$out->putUnsignedVarInt(count($this->actions));
		foreach($this->actions as $action){
			$out->putByte($action::getTypeId());
			$action->write($out);
		}
		$out->putUnsignedVarInt(count($this->filterStrings));
		foreach($this->filterStrings as $string){
			$out->putString($string);
		}
		$out->putLInt($this->filterStringCause);
	}
}
