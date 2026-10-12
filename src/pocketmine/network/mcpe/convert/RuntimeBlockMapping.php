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

namespace pocketmine\network\mcpe\convert;

use pocketmine\block\BlockIds;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\utils\AssumptionFailedError;
use RuntimeException;
use Throwable;
use function count;
use function file_get_contents;
use function get_class;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function ksort;
use const pocketmine\RESOURCE_PATH;

/**
 * @internal
 */
final class RuntimeBlockMapping{

	/** @var CompoundTag[]|null */
	private static ?array $bedrockKnownStates = null;
	/** @var int[]|null */
	private static ?array $stateToRuntimeMap = null;

	private static ?array $runtimeToId = null;
	private static ?array $idToRuntime = null;

	/** @var int */
	private static int $airRid = -1;
	/** @var int */
	private static int $unknownRid = -1;
	/** @var BlockPaletteEntry[]|null */
	private static ?array $dataDrivenBlockPalette = null;

	private function __construct(){
		//NOOP
	}

	public static function isInitialized() : bool{
		return self::$bedrockKnownStates !== null;
	}

	public static function init() : void{
		if(self::$bedrockKnownStates !== null){
			return;
		}

		try{
			$canonicalBlockStatesFile = file_get_contents(RESOURCE_PATH . "vanilla/canonical_block_states.nbt");
			if($canonicalBlockStatesFile === false){
				throw new AssumptionFailedError("Missing required resource file: canonical_block_states.nbt");
			}

			$metaMapRaw = file_get_contents(RESOURCE_PATH . "vanilla/block_state_meta_map.json");
			if($metaMapRaw === false){
				throw new AssumptionFailedError("Missing required resource file: block_state_meta_map.json");
			}

			$metaMap = json_decode($metaMapRaw, true);
			if(!is_array($metaMap)){
				throw new RuntimeException("Invalid block_state_meta_map.json: expected a JSON array");
			}

			$stream = new NetworkBinaryStream($canonicalBlockStatesFile);
			self::$bedrockKnownStates = [];
			self::$stateToRuntimeMap = [];
			self::$runtimeToId = [];
			self::$idToRuntime = [];

			$rid = 0;
			while(!$stream->feof()){
				$state = $stream->getNbtCompoundRoot();
				self::$bedrockKnownStates[] = $state;

				$name = $state->getString("name");
				self::$stateToRuntimeMap[self::blockStateKey($state)] = $rid;

				if(!isset($metaMap[$rid]) || !is_int($metaMap[$rid])){
					throw new RuntimeException("block_state_meta_map.json is out of sync with canonical_block_states.nbt at index " . $rid);
				}
				self::registerMapping($name, $metaMap[$rid], $rid);

				if (self::$airRid === -1 && $name === "minecraft:air") {
					self::$airRid = $rid;
				} elseif (self::$unknownRid === -1 && $name === "minecraft:unknown") {
					self::$unknownRid = $rid;
				}
				$rid++;
			}
			self::initDataDrivenBlocks();
		}catch(Throwable $e){
			self::$bedrockKnownStates = null;
			self::$stateToRuntimeMap = null;
			self::$runtimeToId = null;
			self::$idToRuntime = null;
			self::$dataDrivenBlockPalette = null;
			self::$airRid = -1;
			self::$unknownRid = -1;
			throw $e;
		}
	}

	private static function initDataDrivenBlocks() : void{
		$stream = file_get_contents(RESOURCE_PATH . "vanilla/data_driven_blocks.nbt");
		if($stream === false){
			throw new AssumptionFailedError("Missing required resource file: data_driven_blocks.nbt");
		}

		$nbtStream = new BigEndianNBTStream();
		$root = $nbtStream->readCompressed($stream);

		if(!($root instanceof CompoundTag)){
			throw new RuntimeException("Root NBT tag must be a CompoundTag");
		}

		$palette = [];
		foreach($root->getValue() as $name => $tag){
			if($tag instanceof CompoundTag){
				$palette[] = new BlockPaletteEntry($name, $tag);
			}
		}

		self::$dataDrivenBlockPalette = $palette;
	}

	private static function registerMapping(string $id, int $meta, int $runtimeId) : void{
		self::$idToRuntime[$id][$meta] = $runtimeId;
		self::$runtimeToId[$runtimeId] = [$id, $meta];
	}

	public static function toRuntimeId(string $id, int $meta = 0) : int{
		if(self::$idToRuntime === null){
			self::init();
		}
		return self::$idToRuntime[$id][$meta] ?? self::$unknownRid;
	}

	public static function fromRuntimeId(int $runtimeId) : array{
		if(self::$runtimeToId === null){
			self::init();
		}
		return self::$runtimeToId[$runtimeId] ?? [BlockIds::UNKNOWN, 0];
	}

	public static function getIdFromRuntimeId(int $runtimeId) : string{
		if(self::$runtimeToId === null){
			self::init();
		}
		return self::$runtimeToId[$runtimeId][0] ?? BlockIds::UNKNOWN;
	}

	public static function getMetaFromRuntimeId(int $runtimeId) : int{
		if(self::$runtimeToId === null){
			self::init();
		}
		return self::$runtimeToId[$runtimeId][1] ?? 0;
	}

	public static function getBedrockKnownStates() : array{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
		return self::$bedrockKnownStates;
	}

	public static function getBedrockKnownState(int $runtimeId) : ?CompoundTag{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
		return self::$bedrockKnownStates[$runtimeId] ?? null;
	}

	/**
	 * @return BlockPaletteEntry[]
	 */
	public static function getDataDrivenBlockPalette() : array{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
		return self::$dataDrivenBlockPalette ?? [];
	}

	public static function fromBlockStateNBT(CompoundTag $nbt) : int{
		if(self::$stateToRuntimeMap === null){
			self::init();
		}
		return self::$stateToRuntimeMap[self::blockStateKey($nbt)] ?? self::$unknownRid;
	}

	public static function AIR() : int{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
		return self::$airRid;
	}

	public static function UNKNOWN() : int{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
		return self::$unknownRid;
	}

	private static function blockStateKey(CompoundTag $nbt) : string{
		$children = $nbt->getValue();

		$nameTag = $children["name"] ?? null;
		if($nameTag instanceof StringTag){
			$name = $nameTag->getValue();
		}elseif($nameTag === null){
			throw new RuntimeException('Tag with name "name" not found and no valid default value given');
		}else{
			throw new RuntimeException('Tag with name "name" not of expected type and no valid default value given');
		}

		$statesTag = $children["states"] ?? null;
		if($statesTag !== null && !$statesTag instanceof CompoundTag){
			throw new RuntimeException("Expected a tag of type " . CompoundTag::class . ", got " . get_class($statesTag));
		}
		if($statesTag === null){
			return $name;
		}

		$states = [];
		foreach($statesTag->getValue() as $stateName => $stateTag){
			$stateValue = $stateTag->getValue();
			$states[$stateName] = is_int($stateValue) ? "i" . $stateValue : (is_string($stateValue) ? "s" . $stateValue : "f" . $stateValue);
		}
		if(count($states) === 0){
			return $name;
		}

		ksort($states, SORT_STRING);
		$key = $name;
		foreach($states as $stateName => $stateValue){
			$key .= "\x00" . $stateName . "\x00" . $stateValue;
		}
		return $key;
	}
}
