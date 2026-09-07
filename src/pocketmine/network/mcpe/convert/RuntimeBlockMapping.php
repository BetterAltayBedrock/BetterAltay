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

use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\utils\AssumptionFailedError;
use RuntimeException;
use function count;
use function file_get_contents;
use function is_bool;
use function json_decode;
use const pocketmine\RESOURCE_PATH;

/**
 * @internal
 */
final class RuntimeBlockMapping{

	/** @var int[] */
	private static $legacyToRuntimeMap = [];
	/** @var int[] */
	private static $runtimeToLegacyMap = [];
	/** @var CompoundTag[]|null */
	private static $bedrockKnownStates = null;
	/** @var int[]|null runtime id -> block state network hash (from the game's own data) */
	private static $runtimeToHashMap = null;
	private static $unknownRid = 0;

	private function __construct(){
		//NOOP
	}

	public static function init() : void{
		$canonicalBlockStatesFile = file_get_contents(RESOURCE_PATH . "vanilla/canonical_block_states.nbt");
		if($canonicalBlockStatesFile === false){
			throw new AssumptionFailedError("Missing required resource file");
		}
		$stream = new NetworkBinaryStream($canonicalBlockStatesFile);
		$list = [];
		while(!$stream->feof()){
			$list[] = $stream->getNbtCompoundRoot();
		}
		self::$bedrockKnownStates = $list;

		foreach(self::$bedrockKnownStates as $k => $state){
			if($state->getString("name") === "minecraft:info_update"){
				self::$unknownRid = $k;
				break;
			}
		}

		self::setupLegacyMappings();
	}

	private static function setupLegacyMappings() : void{
		$legacyIdMap = json_decode(file_get_contents(RESOURCE_PATH . "vanilla/block_id_map.json"), true);

		$jsonPath = RESOURCE_PATH . "vanilla/r12_to_current_block_map.json";
		$jsonRaw = file_get_contents($jsonPath);
		if($jsonRaw === false){
			throw new RuntimeException("Missing required resource file: r12_to_current_block_map.json");
		}
		$legacyStateMapJson = json_decode($jsonRaw, true);

		/**
		 * @var int[][] $idToStatesMap string id -> int[] list of candidate state indices
		 */
		$idToStatesMap = [];
		foreach(self::$bedrockKnownStates as $k => $state){
			$name = $state->getString("name");
			$idToStatesMap[$name][] = $k;
		}

		foreach($legacyStateMapJson as $pair){
			$stringId = $pair["id"];
			$id = $legacyIdMap[$stringId] ?? null;
			if($id === null){
				throw new RuntimeException("No legacy ID matches " . $stringId);
			}
			$data = $pair["meta"];
			if($data > Block::INTERNAL_METADATA_MASK){
				//we can't handle metadata with more than 4 bits
				continue;
			}

			$targetState = $pair["blockState"];
			$mappedName = $targetState["name"] ?? "";

			if(!isset($idToStatesMap[$mappedName])){
				throw new RuntimeException("Mapped new state '$mappedName' does not appear in network table");
			}

			$matched = false;
			foreach($idToStatesMap[$mappedName] as $k){
				$networkState = self::$bedrockKnownStates[$k];
				if(self::compareJsonStateWithNbt($targetState, $networkState)){
					self::registerMapping($k, $id, $data);
					$matched = true;
					break;
				}
			}

			if(!$matched){
				throw new RuntimeException("Mapped new state '$mappedName' (meta $data) does not appear in network table");
			}
		}
	}

	private static function compareJsonStateWithNbt(array $jsonState, CompoundTag $nbtState) : bool{
		if(($jsonState["name"] ?? "") !== $nbtState->getString("name")){
			return false;
		}

		$jsonStates = $jsonState["states"] ?? [];
		$nbtStatesTag = $nbtState->getCompoundTag("states");

		if(empty($jsonStates)){
			return $nbtStatesTag === null || count($nbtStatesTag->getValue()) === 0;
		}

		if($nbtStatesTag === null){
			return false;
		}

		foreach($jsonStates as $key => $val){
			if(!$nbtStatesTag->hasTag((string)$key)){
				return false;
			}

			$tag = $nbtStatesTag->getTag((string)$key);
			$tagValue = $tag->getValue();

			if(is_bool($val)){
				$val = $val ? 1 : 0;
			}

			if($tagValue != $val){
				return false;
			}
		}

		return true;
	}

	private static function lazyInit() : void{
		if(self::$bedrockKnownStates === null){
			self::init();
		}
	}

	public static function toStaticRuntimeId(int $id, int $meta = 0) : int{
		self::lazyInit();
		/*
		 * try id+meta first
		 * if not found, try id+0 (strip meta)
		 * if still not found, return update! block
		 */
		return self::$legacyToRuntimeMap[($id << Block::INTERNAL_METADATA_BITS) | $meta] ?? self::$legacyToRuntimeMap[$id << Block::INTERNAL_METADATA_BITS] ?? self::$unknownRid;
	}

	/**
	 * @return int[] [id, meta]
	 */
	public static function fromStaticRuntimeId(int $runtimeId) : array{
		self::lazyInit();
		$v = self::$runtimeToLegacyMap[$runtimeId] ?? (BlockIds::INFO_UPDATE << Block::INTERNAL_METADATA_BITS);
		return [$v >> Block::INTERNAL_METADATA_BITS, $v & Block::INTERNAL_METADATA_MASK];
	}

	private static function registerMapping(int $staticRuntimeId, int $legacyId, int $legacyMeta) : void{
		self::$legacyToRuntimeMap[($legacyId << Block::INTERNAL_METADATA_BITS) | $legacyMeta] = $staticRuntimeId;
		self::$runtimeToLegacyMap[$staticRuntimeId] = ($legacyId << Block::INTERNAL_METADATA_BITS) | $legacyMeta;
	}

	/**
	 * @return CompoundTag[]
	 */
	public static function getBedrockKnownStates() : array{
		self::lazyInit();
		return self::$bedrockKnownStates;
	}

	public static function toStaticRuntimeHash(int $runtimeId) : int{
		self::lazyInit();
		if(self::$runtimeToHashMap === null){
			$map = json_decode(file_get_contents(RESOURCE_PATH . "vanilla/runtime_to_current_hash_map.json"), true);
			self::$runtimeToHashMap = is_array($map) ? $map : [];
		}
		return self::$runtimeToHashMap[$runtimeId] ?? $runtimeId;
	}
}
