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

namespace pocketmine\inventory;

use Generator;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\AssumptionFailedError;
use function array_fill;
use function array_keys;
use function array_map;
use function array_values;
use function base64_decode;
use function count;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function usort;
use const DIRECTORY_SEPARATOR;
use const pocketmine\RESOURCE_PATH;

class CraftingManager{
	/** @var ShapedRecipe[][] */
	protected $shapedRecipes = [];
	/** @var ShapelessRecipe[][] */
	protected $shapelessRecipes = [];
	/** @var FurnaceRecipe[] */
	protected $furnaceRecipes = [];

	/** @var BatchPacket|null */
	private $craftingDataCache;

	public function __construct(){
		$this->init();
	}

	public function init() : void{
		$recipes = json_decode(file_get_contents(RESOURCE_PATH . "vanilla" . DIRECTORY_SEPARATOR . "recipes.json"), true);
		if(!is_array($recipes) || !isset($recipes["recipes"]) || !is_array($recipes["recipes"])){
			throw new AssumptionFailedError("recipes.json root should contain a list of recipes");
		}

		$itemTags = self::loadItemTags();
		$skippedRecipes = 0;

		foreach($recipes["recipes"] as $recipe){
			if(!is_array($recipe) || !isset($recipe["type"])){
				continue;
			}
			if(!$this->initRecipe($recipe, $itemTags)){
				$skippedRecipes++;
			}
		}

		if($skippedRecipes > 0){
			Server::getInstance()->getLogger()->debug("Skipped " . $skippedRecipes . " unsupported recipes while loading recipes.json");
		}

		$this->buildCraftingDataCache();
	}

	/**
	 * @return string[][]
	 * @phpstan-return array<string, list<string>>
	 */
	private static function loadItemTags() : array{
		$data = file_get_contents(RESOURCE_PATH . "vanilla" . DIRECTORY_SEPARATOR . "item_tags.json");
		if($data === false){
			throw new AssumptionFailedError("Missing item_tags.json");
		}
		$tags = json_decode($data, true);
		if(!is_array($tags)){
			throw new AssumptionFailedError("item_tags.json root should contain a map of item tags");
		}
		return $tags;
	}

	/**
	 * @param mixed[] $recipe
	 * @param mixed[][] $itemTags
	 */
	private function initRecipe(array $recipe, array $itemTags) : bool{
		$type = $recipe["type"];
		$block = $recipe["block"] ?? "";

		if($type === CraftingDataPacket::ENTRY_SHAPED){
			if($block !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
				return true;
			}
			if(!isset($recipe["shape"], $recipe["input"], $recipe["output"]) || !is_array($recipe["input"]) || !is_array($recipe["output"])){
				return false;
			}

			$ingredients = [];
			foreach($recipe["input"] as $symbol => $descriptor){
				$items = self::descriptorToItems($descriptor, $itemTags);
				if($items === []){
					return false;
				}
				$ingredients[(string) $symbol] = $items;
			}

			$results = self::outputToItems($recipe["output"]);
			if($results === []){
				return false;
			}

			foreach(self::combineIngredients($ingredients) as $combination){
				$this->registerShapedRecipe(new ShapedRecipe($recipe["shape"], $combination, $results));
			}
			return true;
		}

		if($type === CraftingDataPacket::ENTRY_SHAPELESS || $type === CraftingDataPacket::ENTRY_USER_DATA_SHAPELESS){
			if(!isset($recipe["input"], $recipe["output"]) || !is_array($recipe["input"]) || !is_array($recipe["output"])){
				return false;
			}

			if($block === "furnace"){
				$results = self::outputToItems($recipe["output"]);
				if($results === []){
					return false;
				}
				foreach($recipe["input"] as $descriptor){
					foreach(self::descriptorToItems($descriptor, $itemTags) as $input){
						$this->registerFurnaceRecipe(new FurnaceRecipe($results[0], $input));
					}
				}
				return true;
			}

			if($block !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
				return true;
			}

			$inputLists = [];
			foreach($recipe["input"] as $descriptor){
				$items = self::descriptorToItems($descriptor, $itemTags);
				if($items === []){
					return false;
				}
				$inputLists[] = $items;
			}

			$results = self::outputToItems($recipe["output"]);
			if($results === []){
				return false;
			}

			foreach(self::combineIngredients($inputLists) as $combination){
				$this->registerShapelessRecipe(new ShapelessRecipe(array_values($combination), $results));
			}
			return true;
		}

		if($type === CraftingDataPacket::ENTRY_MULTI || $type === CraftingDataPacket::ENTRY_SMITHING_TRANSFORM || $type === CraftingDataPacket::ENTRY_SMITHING_TRIM){
			//TODO: multi recipes and smithing are not supported by the crafting system yet
			return false;
		}

		return false;
	}

	/**
	 * Resolves a recipe ingredient descriptor (a named item or an item tag) to the items it can match.
	 *
	 * @param mixed[] $descriptor
	 * @param mixed[][] $itemTags
	 *
	 * @return Item[]
	 */
	private static function descriptorToItems(array $descriptor, array $itemTags) : array{
		$type = $descriptor["type"] ?? "name";
		$count = (int) ($descriptor["count"] ?? 1);
		$damage = (int) ($descriptor["auxValue"] ?? -1);

		if($type === "item_tag"){
			$tag = $descriptor["itemTag"] ?? null;
			if(!is_string($tag) || !isset($itemTags[$tag]) || !is_array($itemTags[$tag])){
				return [];
			}

			$items = [];
			foreach($itemTags[$tag] as $stringId){
				if(!is_string($stringId)){
					continue;
				}
				$item = self::itemFromStringId($stringId, $damage, $count);
				if($item !== null){
					$items[] = $item;
				}
			}
			return $items;
		}

		if($type !== "name" || !isset($descriptor["itemId"]) || !is_string($descriptor["itemId"])){
			return [];
		}

		$item = self::itemFromStringId($descriptor["itemId"], $damage, $count);
		return $item !== null ? [$item] : [];
	}

	/**
	 * @param mixed[][] $output
	 *
	 * @return Item[]
	 */
	private static function outputToItems(array $output) : array{
		$items = [];
		foreach($output as $entry){
			if(!is_array($entry) || !isset($entry["id"]) || !is_string($entry["id"])){
				return [];
			}

			$item = self::itemFromStringId($entry["id"], (int) ($entry["damage"] ?? 0), (int) ($entry["count"] ?? 1));
			if($item === null){
				return [];
			}

			if(isset($entry["nbt_b64"]) && is_string($entry["nbt_b64"])){
				$nbt = base64_decode($entry["nbt_b64"], true);
				if($nbt !== false){
					$item->setCompoundTag($nbt);
				}
			}

			$items[] = $item;
		}

		return $items;
	}

	private static function itemFromStringId(string $stringId, int $damage, int $count) : ?Item{
		$translator = ItemTranslator::getInstance();
		[$id, $meta] = $translator->fromStringId($stringId);
		if($stringId === "minecraft:spruce_boat") var_dump($id, $meta);

		$air = $translator->fromStringId("minecraft:air");
		if($id === $air[0] && $meta === $air[1] && $stringId !== "minecraft:air"){
			//unmapped item, usually from a newer version than the one this server implements
			return null;
		}

		//fromStringId() already resolved the metadata for items with a complex (colour etc.) mapping.
		//For all other items, the recipe data's aux value is authoritative (-1 = any damage value).
		if(!$translator->hasComplexMapping($stringId)){
			$meta = $damage;
		}

		return ItemFactory::get($id, $meta, $count);
	}

	/**
	 * Expands item tags into all possible ingredient combinations. Shaped recipes use one item per symbol,
	 * shapeless recipes one item per ingredient.
	 *
	 * @param Item[][] $ingredients
	 *
	 * @return Generator|Item[][]
	 * @phpstan-return Generator<int, array<int|string, Item>, void, void>
	 */
	private static function combineIngredients(array $ingredients) : Generator{
		$keys = array_keys($ingredients);
		if($keys === []){
			yield [];
			return;
		}

		$indexes = array_fill(0, count($keys), 0);
		while(true){
			$combination = [];
			foreach($keys as $i => $key){
				$combination[$key] = $ingredients[$key][$indexes[$i]];
			}
			yield $combination;

			for($i = count($keys) - 1; $i >= 0; --$i){
				if($indexes[$i] + 1 < count($ingredients[$keys[$i]])){
					$indexes[$i]++;
					continue 2;
				}
				$indexes[$i] = 0;
			}
			return;
		}
	}

	/**
	 * Rebuilds the cached CraftingDataPacket.
	 */
	public function buildCraftingDataCache() : void{
		Timings::$craftingDataCacheRebuildTimer->startTiming();
		$pk = new CraftingDataPacket();
		$pk->cleanRecipes = true;

		foreach($this->shapelessRecipes as $list){
			foreach($list as $recipe){
				$pk->addShapelessRecipe($recipe);
			}
		}
		foreach($this->shapedRecipes as $list){
			foreach($list as $recipe){
				$pk->addShapedRecipe($recipe);
			}
		}

		foreach($this->furnaceRecipes as $recipe){
			$pk->addFurnaceRecipe($recipe);
		}

		$pk->encode();

		$batch = new BatchPacket();
		$batch->addPacket($pk);
		$batch->setCompressionLevel(Server::getInstance()->networkCompressionLevel);
		$batch->encode();

		$this->craftingDataCache = $batch;
		Timings::$craftingDataCacheRebuildTimer->stopTiming();
	}

	/**
	 * Returns a pre-compressed CraftingDataPacket for sending to players. Rebuilds the cache if it is not found.
	 */
	public function getCraftingDataPacket() : BatchPacket{
		if($this->craftingDataCache === null){
			$this->buildCraftingDataCache();
		}

		return $this->craftingDataCache;
	}

	/**
	 * Function used to arrange Shapeless Recipe ingredient lists into a consistent order.
	 *
	 * @return int
	 */
	public static function sort(Item $i1, Item $i2){
		//Use spaceship operator to compare each property, then try the next one if they are equivalent.
		($retval = $i1->getId() <=> $i2->getId()) === 0 && ($retval = $i1->getDamage() <=> $i2->getDamage()) === 0 && ($retval = $i1->getCount() <=> $i2->getCount()) === 0;

		return $retval;
	}

	/**
	 * @param Item[] $items
	 *
	 * @return Item[]
	 */
	private static function pack(array $items) : array{
		/** @var Item[] $result */
		$result = [];

		foreach($items as $i => $item){
			foreach($result as $otherItem){
				if($item->equals($otherItem)){
					$otherItem->setCount($otherItem->getCount() + $item->getCount());
					continue 2;
				}
			}

			//No matching item found
			$result[] = clone $item;
		}

		return $result;
	}

	/**
	 * @param Item[] $outputs
	 */
	private static function hashOutputs(array $outputs) : string{
		$outputs = self::pack($outputs);
		usort($outputs, [self::class, "sort"]);
		foreach($outputs as $o){
			//this reduces accuracy of hash, but it's necessary to deal with recipe book shift-clicking stupidity
			$o->setCount(1);
		}

		return json_encode($outputs);
	}

	/**
	 * @return ShapelessRecipe[][]
	 */
	public function getShapelessRecipes() : array{
		return $this->shapelessRecipes;
	}

	/**
	 * @return ShapedRecipe[][]
	 */
	public function getShapedRecipes() : array{
		return $this->shapedRecipes;
	}

	/**
	 * @return FurnaceRecipe[]
	 */
	public function getFurnaceRecipes() : array{
		return $this->furnaceRecipes;
	}

	public function registerShapedRecipe(ShapedRecipe $recipe) : void{
		$this->shapedRecipes[self::hashOutputs($recipe->getResults())][] = $recipe;

		$this->craftingDataCache = null;
	}

	public function registerShapelessRecipe(ShapelessRecipe $recipe) : void{
		$this->shapelessRecipes[self::hashOutputs($recipe->getResults())][] = $recipe;

		$this->craftingDataCache = null;
	}

	public function registerFurnaceRecipe(FurnaceRecipe $recipe) : void{
		$input = $recipe->getInput();
		$this->furnaceRecipes[$input->getId() . ":" . ($input->hasAnyDamageValue() ? "?" : $input->getDamage())] = $recipe;
		$this->craftingDataCache = null;
	}

	/**
	 * @param Item[] $outputs
	 */
	public function matchRecipe(CraftingGrid $grid, array $outputs) : ?CraftingRecipe{
		//TODO: try to match special recipes before anything else (first they need to be implemented!)

		$outputHash = self::hashOutputs($outputs);

		if(isset($this->shapedRecipes[$outputHash])){
			foreach($this->shapedRecipes[$outputHash] as $recipe){
				if($recipe->matchesCraftingGrid($grid)){
					return $recipe;
				}
			}
		}

		if(isset($this->shapelessRecipes[$outputHash])){
			foreach($this->shapelessRecipes[$outputHash] as $recipe){
				if($recipe->matchesCraftingGrid($grid)){
					return $recipe;
				}
			}
		}

		return null;
	}

	/**
	 * @param Item[] $outputs
	 *
	 * @return CraftingRecipe[]|Generator
	 * @phpstan-return Generator<int, CraftingRecipe, void, void>
	 */
	public function matchRecipeByOutputs(array $outputs) : Generator{
		//TODO: try to match special recipes before anything else (first they need to be implemented!)

		$outputHash = self::hashOutputs($outputs);

		if(isset($this->shapedRecipes[$outputHash])){
			foreach($this->shapedRecipes[$outputHash] as $recipe){
				yield $recipe;
			}
		}

		if(isset($this->shapelessRecipes[$outputHash])){
			foreach($this->shapelessRecipes[$outputHash] as $recipe){
				yield $recipe;
			}
		}
	}

	public function matchFurnaceRecipe(Item $input) : ?FurnaceRecipe{
		return $this->furnaceRecipes[$input->getId() . ":" . $input->getDamage()] ?? $this->furnaceRecipes[$input->getId() . ":?"] ?? null;
	}

	/**
	 * @deprecated
	 */
	public function registerRecipe(Recipe $recipe) : void{
		$recipe->registerToCraftingManager($this);
	}
}
