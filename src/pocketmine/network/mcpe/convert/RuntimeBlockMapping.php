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
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\AssumptionFailedError;
use RuntimeException;
use function count;
use function file_get_contents;
use function json_decode;
use const pocketmine\RESOURCE_PATH;

/**
 * @internal
 */
final class RuntimeBlockMapping{

	/** @var int[] */
	private static array $legacyToRuntimeMap = [];
	/** @var int[] */
	private static array $runtimeToLegacyMap = [];
	/** @var CompoundTag[]|null */
	private static ?array $bedrockKnownStates = null;
	/** @var int[] runtime id -> block state network hash */
	private static array $runtimeIdToHashMap = [];
	private static int $unknownRid = 0;
	/** @var array<string, array<int, int>> */
	private static array $skullFacingToRuntimeIdMap = [];

	private function __construct(){
		//NOOP
	}

	public static function init() : void{
		$paletteRaw = file_get_contents(RESOURCE_PATH . "vanilla/block_palette.nbt");
		if($paletteRaw === false){
			throw new AssumptionFailedError("Missing required resource file: block_palette.nbt");
		}

		$nbtStream = new BigEndianNBTStream();
		$root = $nbtStream->readCompressed($paletteRaw);

		if(!($root instanceof CompoundTag)){
			throw new RuntimeException("Root NBT tag must be a CompoundTag");
		}

		$blocksList = $root->getListTag("blocks");
		if($blocksList === null){
			throw new RuntimeException("Missing 'blocks' tag in block_palette.nbt");
		}

		$netStream = new NetworkLittleEndianNBTStream();

		/** @var CompoundTag[] $list */
		$list = [];
		foreach($blocksList->getValue() as $k => $blockCompound){
			if($blockCompound instanceof CompoundTag){
				if($blockCompound->hasTag("network_id")){
					self::$runtimeIdToHashMap[$k] = $blockCompound->getInt("network_id");
					$blockCompound->removeTag("network_id");
				}

				if($blockCompound->hasTag("name_hash")){
					$blockCompound->removeTag("name_hash");
				}

				$state = $netStream->read($netStream->write($blockCompound));
				if($state instanceof CompoundTag){
					$list[] = $state;
				}
			}
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

		if(self::$bedrockKnownStates === null){
			throw new RuntimeException("Bedrock known states are not initialized");
		}

		/**
		 * @var int[][] $idToStatesMap string id -> int[] list of candidate state indices
		 */
		$idToStatesMap = [];
		foreach(self::$bedrockKnownStates as $k => $state){
			$name = $state->getString("name");
			$idToStatesMap[$name][] = $k;
			if(str_ends_with($name, "_head") || str_ends_with($name, "_skull")){
				$states = $state->getCompoundTag("states");
				if($states !== null){
					$facing = $states->getInt("facing_direction", 0);
					self::$skullFacingToRuntimeIdMap[$name][$facing] = $k;
				}
			}
		}

		foreach($legacyStateMapJson as $pair){
			$stringId = $pair["id"];
			$id = $legacyIdMap[$stringId] ?? null;
			if($id === null){
				throw new RuntimeException("No legacy ID matches " . $stringId);
			}
			$data = $pair["meta"];
			if($data > 0xf){
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

		if(count($jsonStates) === 0){
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
		return self::$legacyToRuntimeMap[($id << 4) | $meta] ?? self::$legacyToRuntimeMap[$id << 4] ?? self::$unknownRid;
	}

	/**
	 * @return int[] [id, meta]
	 */
	public static function fromStaticRuntimeId(int $runtimeId) : array{
		self::lazyInit();
		$v = self::$runtimeToLegacyMap[$runtimeId] ?? (BlockIds::INFO_UPDATE << 4);
		return [$v >> 4, $v & 0xf];
	}

	private static function registerMapping(int $staticRuntimeId, int $legacyId, int $legacyMeta) : void{
		self::$legacyToRuntimeMap[($legacyId << 4) | $legacyMeta] = $staticRuntimeId;
		self::$runtimeToLegacyMap[$staticRuntimeId] = ($legacyId << 4) | $legacyMeta;
	}

	/**
	 * @return CompoundTag[]
	 */
	public static function getBedrockKnownStates() : array{
		self::lazyInit();
		return self::$bedrockKnownStates ?? [];
	}

	/**
	 * @return int[]
	 */
	public static function getRuntimeIdToHashMap() : array{
		self::lazyInit();
		return self::$runtimeIdToHashMap;
	}

	public static function toStaticRuntimeHash(int $runtimeId) : int{
		self::lazyInit();
		return self::$runtimeIdToHashMap[$runtimeId] ?? throw new RuntimeException("Unknown runtime ID $runtimeId");
	}

	public static function fromSkullFacing(string $name, int $facing) : ?int{
		self::lazyInit();
		return self::$skullFacingToRuntimeIdMap[$name][$facing] ?? null;
	}
}