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
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\utils\AssumptionFailedError;
use RuntimeException;
use function file_get_contents;
use function json_decode;
use const pocketmine\RESOURCE_PATH;

/**
 * @internal
 */
final class RuntimeBlockMapping{

	/** @var CompoundTag[]|null */
	private static $bedrockKnownStates = null;
	/** @var int[]|null */
	private static $stateToRuntimeMap = [];

	private static $runtimeToId = [];
	private static $idToRuntime = [];

	/** @var int */
	private static $airRid = -1;
	/** @var int */
	private static $unknownRid = -1;
	/** @var BlockPaletteEntry[]|null */
	private static ?array $dataDrivenBlockPalette = null;

	private function __construct(){
		//NOOP
	}

	public static function init() : void{
		$canonicalBlockStatesFile = file_get_contents(RESOURCE_PATH . "vanilla/canonical_block_states.nbt");
		if($canonicalBlockStatesFile === false){
			throw new AssumptionFailedError("Missing required resource file");
		}

		$metaMap = json_decode(file_get_contents(RESOURCE_PATH . "vanilla/block_state_meta_map.json"));
		$stream = new NetworkBinaryStream($canonicalBlockStatesFile);
		self::$bedrockKnownStates = [];

		$rid = 0;
		while(!$stream->feof()){
			$state = $stream->getNbtCompoundRoot();
			self::$bedrockKnownStates[] = $state;

			$name = $state->getString("name");
			self::$stateToRuntimeMap[self::hashBlockStateNBT($name, $state->getCompoundTag("states"))] = $rid;
			self::registerMapping($name, $metaMap[$rid], $rid);

			if (self::$airRid === -1 && $name === "minecraft:air") {
				self::$airRid = $rid;
			} elseif (self::$unknownRid === -1 && $name === "minecraft:unknown") {
				self::$unknownRid = $rid;
			}
			$rid++;
		}
		self::initDataDrivenBlocks();
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

	private static function lazyInit() : void{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
	}

	private static function registerMapping(string $id, int $meta, int $runtimeId) : void{
		self::$idToRuntime[$id][$meta] = $runtimeId;
		self::$runtimeToId[$runtimeId] = [$id, $meta];
	}

	public static function toRuntimeId(string $id, int $meta = 0) : int{
		self::lazyInit();
		return self::$idToRuntime[$id][$meta] ?? self::UNKNOWN();
	}

	public static function fromRuntimeId(int $runtimeId) : array{
		self::lazyInit();
		return self::$runtimeToId[$runtimeId] ?? [BlockIds::UNKNOWN, 0];
	}

	public static function getIdFromRuntimeId(int $runtimeId) : string{
		return self::fromRuntimeId($runtimeId)[0];
	}

	public static function getMetaFromRuntimeId(int $runtimeId) : int{
		return self::fromRuntimeId($runtimeId)[1];
	}

	public static function getBedrockKnownStates() : array{
		self::lazyInit();
		return self::$bedrockKnownStates;
	}

	/**
	 * @return BlockPaletteEntry[]
	 */
	public static function getDataDrivenBlockPalette() : array{
		self::lazyInit();
		return self::$dataDrivenBlockPalette ?? [];
	}

	public static function fromBlockStateNBT(CompoundTag $nbt) : int{
		self::lazyInit();
		$hash = self::hashBlockStateNBT($nbt->getString("name"), $nbt->getCompoundTag("states"));
		return self::$stateToRuntimeMap[$hash] ?? self::UNKNOWN();
	}

	public static function AIR() : int{
		self::lazyInit();
		return self::$airRid;
	}

	public static function UNKNOWN() : int{
		self::lazyInit();
		return self::$unknownRid;
	}

	/**
	 * @param string           $name
	 * @param CompoundTag|null $blockState
	 *
	 * @return string
	 */
	private static function hashBlockStateNBT(string $name, ?CompoundTag $blockState = null) : string{
		$states = [];
		if($blockState !== null){
			foreach($blockState->getValue() as $key => $tag){
				$states[$key] = $tag->getValue();
			}
			ksort($states);
		}

		return hash('fnv1a64', $name . ":" . json_encode($states));
	}
}
