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

namespace pocketmine\item;

use InvalidArgumentException;
use pocketmine\block\BlockIds;
use pocketmine\block\material\ColorType;
use pocketmine\block\material\WoodType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\ItemTranslator;
use RuntimeException;
use TypeError;
use function constant;
use function defined;
use function explode;
use function get_class;
use function gettype;
use function is_numeric;
use function is_object;
use function is_string;
use function mb_strtoupper;
use function str_replace;
use function trim;

/**
 * Manages Item instance creation and registration
 */
class ItemFactory{

	/**
	 * @var Item[]
	 * @phpstan-var Item[]
	 */
	private static array $list = [];

	/**
	 * @return void
	 */
	public static function init() : void{
		if (!empty(self::$list)) {
			return;
		}

		self::registerItem(new Shovel(ItemIds::IRON_SHOVEL, 0, "Iron Shovel", TieredTool::TIER_IRON));
		self::registerItem(new Pickaxe(ItemIds::IRON_PICKAXE, 0, "Iron Pickaxe", TieredTool::TIER_IRON));
		self::registerItem(new Axe(ItemIds::IRON_AXE, 0, "Iron Axe", TieredTool::TIER_IRON));
		self::registerItem(new FlintSteel());
		self::registerItem(new Apple());
		self::registerItem(new Bow());
		self::registerItem(new Arrow());
		self::registerItem(new Coal());
		self::registerItem(new Charcoal());
		self::registerItem(new Item(ItemIds::DIAMOND, 0, "Diamond"));
		self::registerItem(new Item(ItemIds::IRON_INGOT, 0, "Iron Ingot"));
		self::registerItem(new Item(ItemIds::GOLD_INGOT, 0, "Gold Ingot"));
		self::registerItem(new Sword(ItemIds::IRON_SWORD, 0, "Iron Sword", TieredTool::TIER_IRON));
		self::registerItem(new Sword(ItemIds::WOODEN_SWORD, 0, "Wooden Sword", TieredTool::TIER_WOODEN));
		self::registerItem(new Shovel(ItemIds::WOODEN_SHOVEL, 0, "Wooden Shovel", TieredTool::TIER_WOODEN));
		self::registerItem(new Pickaxe(ItemIds::WOODEN_PICKAXE, 0, "Wooden Pickaxe", TieredTool::TIER_WOODEN));
		self::registerItem(new Axe(ItemIds::WOODEN_AXE, 0, "Wooden Axe", TieredTool::TIER_WOODEN));
		self::registerItem(new Sword(ItemIds::STONE_SWORD, 0, "Stone Sword", TieredTool::TIER_STONE));
		self::registerItem(new Shovel(ItemIds::STONE_SHOVEL, 0, "Stone Shovel", TieredTool::TIER_STONE));
		self::registerItem(new Pickaxe(ItemIds::STONE_PICKAXE, 0, "Stone Pickaxe", TieredTool::TIER_STONE));
		self::registerItem(new Axe(ItemIds::STONE_AXE, 0, "Stone Axe", TieredTool::TIER_STONE));
		self::registerItem(new Sword(ItemIds::DIAMOND_SWORD, 0, "Diamond Sword", TieredTool::TIER_DIAMOND));
		self::registerItem(new Shovel(ItemIds::DIAMOND_SHOVEL, 0, "Diamond Shovel", TieredTool::TIER_DIAMOND));
		self::registerItem(new Pickaxe(ItemIds::DIAMOND_PICKAXE, 0, "Diamond Pickaxe", TieredTool::TIER_DIAMOND));
		self::registerItem(new Axe(ItemIds::DIAMOND_AXE, 0, "Diamond Axe", TieredTool::TIER_DIAMOND));
		self::registerItem(new Sword(ItemIds::NETHERITE_SWORD, 0, "Netherite Sword", TieredTool::TIER_NETHERITE));
		self::registerItem(new Shovel(ItemIds::NETHERITE_SHOVEL, 0, "Netherite Shovel", TieredTool::TIER_NETHERITE));
		self::registerItem(new Pickaxe(ItemIds::NETHERITE_PICKAXE, 0, "Netherite Pickaxe", TieredTool::TIER_NETHERITE));
		self::registerItem(new Axe(ItemIds::NETHERITE_AXE, 0, "Netherite Axe", TieredTool::TIER_NETHERITE));
		self::registerItem(new Stick());
		self::registerItem(new Bowl());
		self::registerItem(new MushroomStew());
		self::registerItem(new Sword(ItemIds::GOLDEN_SWORD, 0, "Gold Sword", TieredTool::TIER_GOLD));
		self::registerItem(new Shovel(ItemIds::GOLDEN_SHOVEL, 0, "Gold Shovel", TieredTool::TIER_GOLD));
		self::registerItem(new Pickaxe(ItemIds::GOLDEN_PICKAXE, 0, "Gold Pickaxe", TieredTool::TIER_GOLD));
		self::registerItem(new Axe(ItemIds::GOLDEN_AXE, 0, "Gold Axe", TieredTool::TIER_GOLD));
		self::registerItem(new Sword(ItemIds::COPPER_SWORD, 0, "Copper Sword", TieredTool::TIER_COPPER));
		self::registerItem(new Axe(ItemIds::COPPER_AXE, 0, "Copper Axe", TieredTool::TIER_COPPER));
		self::registerItem(new Pickaxe(ItemIds::COPPER_PICKAXE, 0, "Copper Pickaxe", TieredTool::TIER_COPPER));
		self::registerItem(new Shovel(ItemIds::COPPER_SHOVEL, 0, "Copper Shovel", TieredTool::TIER_COPPER));
		self::registerItem(new StringItem());
		self::registerItem(new Item(ItemIds::FEATHER, 0, "Feather"));
		self::registerItem(new Item(ItemIds::GUNPOWDER, 0, "Gunpowder"));
		self::registerItem(new Hoe(ItemIds::WOODEN_HOE, 0, "Wooden Hoe", TieredTool::TIER_WOODEN));
		self::registerItem(new Hoe(ItemIds::STONE_HOE, 0, "Stone Hoe", TieredTool::TIER_STONE));
		self::registerItem(new Hoe(ItemIds::IRON_HOE, 0, "Iron Hoe", TieredTool::TIER_IRON));
		self::registerItem(new Hoe(ItemIds::DIAMOND_HOE, 0, "Diamond Hoe", TieredTool::TIER_DIAMOND));
		self::registerItem(new Hoe(ItemIds::NETHERITE_HOE, 0, "Netherite Hoe", TieredTool::TIER_NETHERITE));
		self::registerItem(new Hoe(ItemIds::GOLDEN_HOE, 0, "Golden Hoe", TieredTool::TIER_GOLD));
		self::registerItem(new Hoe(ItemIds::COPPER_HOE, 0, "Copper Hoe", TieredTool::TIER_COPPER));
		self::registerItem(new WheatSeeds());
		self::registerItem(new Item(ItemIds::WHEAT, 0, "Wheat"));
		self::registerItem(new Bread());
		self::registerItem(new LeatherCap());
		self::registerItem(new LeatherTunic());
		self::registerItem(new LeatherPants());
		self::registerItem(new LeatherBoots());
		self::registerItem(new ChainHelmet());
		self::registerItem(new ChainChestplate());
		self::registerItem(new ChainLeggings());
		self::registerItem(new ChainBoots());
		self::registerItem(new IronHelmet());
		self::registerItem(new IronChestplate());
		self::registerItem(new IronLeggings());
		self::registerItem(new IronBoots());
		self::registerItem(new DiamondHelmet());
		self::registerItem(new DiamondChestplate());
		self::registerItem(new DiamondLeggings());
		self::registerItem(new DiamondBoots());
		self::registerItem(new NetheriteHelmet());
		self::registerItem(new NetheriteChestplate());
		self::registerItem(new NetheriteLeggings());
		self::registerItem(new NetheriteBoots());
		self::registerItem(new GoldHelmet());
		self::registerItem(new GoldChestplate());
		self::registerItem(new GoldLeggings());
		self::registerItem(new GoldBoots());
		self::registerItem(new Item(ItemIds::FLINT, 0, "Flint"));
		self::registerItem(new RawPorkchop());
		self::registerItem(new CookedPorkchop());
		self::registerItem(new PaintingItem());
		self::registerItem(new GoldenApple());
		self::registerSignItems();
		self::registerDoorItems();
		self::registerShulkerBoxes();
		self::registerItem(new Bucket());
		self::registerItem(new WaterBucket());
		self::registerItem(new LavaBucket());
		self::registerItem(new MilkBucket());

		self::registerItem(new Minecart());
		self::registerItem(new Saddle());
		self::registerItem(new Redstone());
		self::registerItem(new Snowball());
		self::registerBoatItems();
		self::registerItem(new BambooRaft());
		self::registerItem(new Item(ItemIds::LEATHER, 0, "Leather"));
		//TODO: KELP
		self::registerItem(new Item(ItemIds::BRICK, 0, "Brick"));
		self::registerItem(new Item(ItemIds::CLAY_BALL, 0, "Clay"));
		self::registerItem(new ItemBlock(BlockIds::REEDS, 0, ItemIds::SUGAR_CANE));
		self::registerItem(new Item(ItemIds::PAPER, 0, "Paper"));
		self::registerItem(new Book());
		self::registerItem(new Item(ItemIds::SLIME_BALL, 0, "Slimeball"));
		//TODO: CHEST_MINECART

		self::registerItem(new Egg());
		self::registerItem(new BrownEgg());
		self::registerItem(new BlueEgg());
		self::registerItem(new Compass());
		self::registerItem(new FishingRod());
		self::registerItem(new Clock());
		self::registerItem(new Item(ItemIds::GLOWSTONE_DUST, 0, "Glowstone Dust"));
		self::registerItem(new RawFish());
		self::registerItem(new RawFish(ItemIds::SALMON, "Raw Salmon", 2, 0.2));
		self::registerItem(new CookedFish());
		self::registerItem(new CookedFish(ItemIds::COOKED_SALMON, "Cooked Salmon", 6, 9.6));
		self::registerDyes();
		self::registerItem(new Item(ItemIds::LAPIS_LAZULI, 0, "Lapis Lazuli"));
		self::registerItem(new Item(ItemIds::INK_SAC, 0, "Ink Sac"));
		self::registerItem(new Item(ItemIds::COCOA_BEANS, 0, "Cocoa Beans"));
		self::registerItem(new Item(ItemIds::BONE_MEAL, 0, "Bone Meal"));
		self::registerItem(new Item(ItemIds::BONE, 0, "Bone"));
		self::registerItem(new Item(ItemIds::SUGAR, 0, "Sugar"));
		self::registerItem(new ItemBlock(BlockIds::CAKE, 0, ItemIds::CAKE));
		self::registerItem(new Bed());
//		self::registerItem(new ItemBlock(BlockIds::UNPOWERED_REPEATER, 0, ItemIds::REPEATER));
		self::registerItem(new Cookie());
		self::registerItem(new Map());
		self::registerItem(new Shears());
		self::registerItem(new Melon());
		self::registerItem(new PumpkinSeeds());
		self::registerItem(new MelonSeeds());
		self::registerItem(new RawBeef());
		self::registerItem(new Steak());
		self::registerItem(new RawChicken());
		self::registerItem(new CookedChicken());
		self::registerItem(new RottenFlesh());
		self::registerItem(new EnderPearl());
		self::registerItem(new BlazeRod());
		self::registerItem(new Item(ItemIds::GHAST_TEAR, 0, "Ghast Tear"));
		self::registerItem(new Item(ItemIds::GOLD_NUGGET, 0, "Gold Nugget"));
		self::registerItem(new ItemBlock(BlockIds::NETHER_WART, 0, ItemIds::NETHER_WART));
		self::registerItem(new Potion());
		self::registerItem(new GlassBottle());
		self::registerItem(new SpiderEye());
		self::registerItem(new Item(ItemIds::FERMENTED_SPIDER_EYE, 0, "Fermented Spider Eye"));
		self::registerItem(new Item(ItemIds::BLAZE_POWDER, 0, "Blaze Powder"));
		self::registerItem(new Item(ItemIds::MAGMA_CREAM, 0, "Magma Cream"));
		self::registerItem(new ItemBlock(BlockIds::BREWING_STAND, 0, ItemIds::BREWING_STAND));
		self::registerItem(new ItemBlock(BlockIds::CAULDRON, 0, ItemIds::CAULDRON));
		//TODO: ENDER_EYE
		self::registerItem(new Item(ItemIds::GLISTERING_MELON_SLICE, 0, "Glistering Melon"));
		self::registerSpawnEggs();
		self::registerItem(new ExperienceBottle());
		//TODO: FIREBALL
		self::registerItem(new WritableBook());
		self::registerItem(new WrittenBook());
		self::registerItem(new Item(ItemIds::EMERALD, 0, "Emerald"));
		self::registerItem(new ItemBlock(BlockIds::FRAME, 0, ItemIds::FRAME));
		self::registerItem(new ItemBlock(BlockIds::GLOW_FRAME, 0, ItemIds::GLOW_FRAME));
		self::registerItem(new ItemBlock(BlockIds::FLOWER_POT, 0, ItemIds::FLOWER_POT));
		self::registerItem(new Carrot());
		self::registerItem(new Potato());
		self::registerItem(new BakedPotato());
		self::registerItem(new PoisonousPotato());
		self::registerItem(new EmptyMap());
		self::registerItem(new GoldenCarrot());
		self::registerSkulls();
		//TODO: CARROTONASTICK
		self::registerItem(new Item(ItemIds::NETHER_STAR, 0, "Nether Star"));
		self::registerItem(new PumpkinPie());
		self::registerItem(new Fireworks());
		self::registerItem(new Item(ItemIds::FIREWORK_STAR, 0, "Fireworks Charge"));
		self::registerItem(new EnchantedBook());
		//self::registerItem(new ItemBlock(Block::COMPARATOR_BLOCK, 0, Item::COMPARATOR));
		self::registerItem(new Item(ItemIds::NETHERBRICK, 0, "Nether Brick"));
		self::registerItem(new Item(ItemIds::QUARTZ, 0, "Nether Quartz"));
		//TODO: MINECART_WITH_TNT
		//TODO: HOPPER_MINECART
		self::registerItem(new Item(ItemIds::PRISMARINE_SHARD, 0, "Prismarine Shard"));
		self::registerItem(new ItemBlock(BlockIds::HOPPER, 0, ItemIds::HOPPER));
		self::registerItem(new RawRabbit());
		self::registerItem(new CookedRabbit());
		self::registerItem(new RabbitStew());
		self::registerItem(new Item(ItemIds::RABBIT_FOOT, 0, "Rabbit's Foot"));
		self::registerItem(new Item(ItemIds::RABBIT_HIDE, 0, "Rabbit Hide"));
		self::registerItem(new LeatherHorseArmor());
		self::registerItem(new IronHorseArmor());
		self::registerItem(new GoldenHorseArmor());
		self::registerItem(new DiamondHorseArmor());
		self::registerItem(new Item(ItemIds::LEAD, 0, "Lead"));
		//TODO: NAMETAG
		self::registerItem(new Item(ItemIds::PRISMARINE_CRYSTALS, 0, "Prismarine Crystals"));
		self::registerItem(new RawMutton());
		self::registerItem(new CookedMutton());
		self::registerItem(new ArmorStand());
		self::registerItem(new EndCrystal());
		self::registerItem(new ChorusFruit());
		self::registerItem(new Item(ItemIds::POPPED_CHORUS_FRUIT, 0, "Popped Chorus Fruit"));

		self::registerItem(new Item(ItemIds::DRAGON_BREATH, 0, "Dragon's Breath"));
		self::registerItem(new SplashPotion());

		//TODO: LINGERING_POTION
		//TODO: SPARKLER
		//TODO: COMMAND_BLOCK_MINECART
		self::registerItem(new Elytra());
		self::registerItem(new Item(ItemIds::SHULKER_SHELL, 0, "Shulker Shell"));
		self::registerItem(new Banner());
		//TODO: MEDICINE
		//TODO: BALLOON
		//TODO: RAPID_FERTILIZER
		self::registerItem(new Totem());
		self::registerItem(new Item(ItemIds::BLEACH, 0, "Bleach")); //EDU
		self::registerItem(new Item(ItemIds::IRON_NUGGET, 0, "Iron Nugget"));
		//TODO: ICE_BOMB

		//TODO: TRIDENT

		self::registerItem(new Beetroot());
		self::registerItem(new BeetrootSeeds());
		self::registerItem(new BeetrootSoup());
		self::registerItem(new Clownfish());
		self::registerItem(new Pufferfish());
		self::registerItem(new DriedKelp());
		self::registerItem(new Item(ItemIds::NAUTILUS_SHELL, 0, "Nautilus Shell"));
		self::registerItem(new GoldenAppleEnchanted());
		self::registerItem(new Item(ItemIds::HEART_OF_THE_SEA, 0, "Heart of the Sea"));
		self::registerItem(new Item(ItemIds::TURTLE_SCUTE, 0, "Scute"));
		self::registerItem(new TurtleHelmet());
		self::registerMusicDiscs();
		self::registerItem(new Item(ItemIds::DISC_FRAGMENT_5, 0, "Disc Fragment"));
		self::registerItem(new Shield());
		self::registerItem(new NetheriteIngot());
		self::registerItem(new NetheriteScrap());
		self::registerItem(new Item(ItemIds::HONEYCOMB, 0, "Honeycomb"));
		self::registerItem(new HoneyBottle());
		self::registerItem(new Spyglass());
		self::registerItem(new CopperHorseArmor());
		self::registerItem(new CopperHelmet());
		self::registerItem(new CopperChestplate());
		self::registerItem(new CopperLeggings());
		self::registerItem(new CopperBoots());
		self::registerItem(new Item(ItemIds::COPPER_INGOT, 0, "Copper Ingot"));
		self::registerItem(new Item(ItemIds::COPPER_NUGGET, 0, "Copper Nugget"));
		self::registerFenceGates();
		self::registerItem(new ItemBlock(BlockIds::FURNACE, 0, BlockIds::FURNACE));
		self::registerItem(new ItemBlock(BlockIds::BLAST_FURNACE, 0, BlockIds::BLAST_FURNACE));
	}

	/**
	 * Registers an item type into the index. Plugins may use this method to register new item types or override existing
	 * ones.
	 *
	 * NOTE: If you are registering a new item type, you will need to add it to the creative inventory yourself - it
	 * will not automatically appear there.
	 *
	 * @return void
	 * @throws RuntimeException if something attempted to override an already-registered item without specifying the
	 * $override parameter.
	 */
	public static function registerItem(Item $item, bool $override = false){
		$id = $item->getId();
		if(!$override and self::isRegistered($id)){
			throw new RuntimeException("Trying to overwrite an already registered item");
		}

		self::$list[$id] = clone $item;
	}

	/**
	 * Returns an instance of the Item with the specified id, meta, count and NBT.
	 *
	 * @param CompoundTag|string|null $tags
	 *
	 * @throws TypeError
	 */
	public static function get(string $id, int $meta = 0, int $count = 1, $tags = null) : Item{
		if(!is_string($tags) and !($tags instanceof CompoundTag) and $tags !== null){
			throw new TypeError("`tags` argument must be a string or CompoundTag instance, " . (is_object($tags) ? "instance of " . get_class($tags) : gettype($tags)) . " given");
		}

		try{
			/** @var Item|null $listed */
			$listed = self::$list[$id] ?? null;
			if($listed !== null){
				$item = clone $listed;
			}elseif(($blockId = ItemTranslator::getInstance()->toBlockId($id)) !== null){ //intentionally excludes negatives because extended blocks aren't supported yet
				/* Blocks must have a damage value 0-15, but items can have damage value -1 to indicate that they are
				 * crafting ingredients with any-damage. */
				$item = new ItemBlock($blockId, $meta, $id);
			}else{
				$item = new ItemBlock(BlockIds::UNKNOWN, $meta, $id);
			}
		}catch(RuntimeException $e){
			throw new InvalidArgumentException("Item ID $id is invalid or out of bounds");
		}

		$item->setDamage($meta);
		$item->setCount($count);
		$item->setCompoundTag($tags);
		return $item;
	}

	/**
	 * Tries to parse the specified string into Item ID/meta identifiers, and returns Item instances it created.
	 *
	 * Example accepted formats:
	 * - `diamond_pickaxe:5`
	 * - `minecraft:string`
	 * - `351:4 (lapis lazuli ID:meta)`
	 *
	 * If multiple item instances are to be created, their identifiers must be comma-separated, for example:
	 * `diamond_pickaxe,wooden_shovel:18,iron_ingot`
	 *
	 * @return Item[]|Item
	 *
	 * @throws InvalidArgumentException if the given string cannot be parsed as an item identifier
	 */
	public static function fromString(string $str, bool $multiple = false){
		if($multiple){
			$blocks = [];
			foreach(explode(",", $str) as $b){
				$blocks[] = self::fromStringSingle($b);
			}

			return $blocks;
		}else{
			return self::fromStringSingle($str);
		}
	}

	public static function fromStringSingle(string $str) : Item{
		$b = explode(":", str_replace([" ", "minecraft:"], ["_", ""], trim($str)));
		if(!isset($b[1])){
			$meta = 0;
		}elseif(is_numeric($b[1])){
			$meta = (int) $b[1];
		}else{
			throw new InvalidArgumentException("Unable to parse \"" . $b[1] . "\" from \"" . $str . "\" as a valid meta value");
		}

		if(is_numeric($b[0])){
			[$rid, ] = ItemTranslator::getInstance()->legacyToNetworkId((int) $b[0], $meta);
			[$id, ] = ItemTranslator::getInstance()->fromNetworkId($rid, $meta);
			$item = self::get($id, $meta);
		}elseif(defined(ItemIds::class . "::" . mb_strtoupper($b[0]))){
			$item = self::get(constant(ItemIds::class . "::" . mb_strtoupper($b[0])), $meta);
		}elseif(defined(BlockIds::class . "::" . mb_strtoupper($b[0]))){
			$item = self::get(constant(BLockIds::class . "::" . mb_strtoupper($b[0])), $meta);
		}else{
			throw new InvalidArgumentException("Unable to resolve \"" . $str . "\" to a valid item");
		}

		return $item;
	}

	/**
	 * Returns whether the specified item ID is already registered in the item factory.
	 */
	public static function isRegistered(string $id) : bool{
		return isset(self::$list[$id]) && self::$list[$id] !== null;
	}

	private static function registerSignItems() : void{
		foreach(WoodType::values() as $type){
			self::registerItem(new Sign($type));
		}
		self::registerItem(new Sign(new WoodType("bamboo", "Bamboo")));
	}

	private static function registerBoatItems() : void{
		foreach(WoodType::values() as $type){
			self::registerItem(new Boat($type));
		}
	}

	private static function registerDoorItems() : void {
		self::registerItem(new ItemBlock(BlockIds::WOODEN_DOOR, 0, ItemIds::WOODEN_DOOR));
		self::registerItem(new ItemBlock(BlockIds::IRON_DOOR, 0, ItemIds::IRON_DOOR));
		self::registerItem(new ItemBlock(BlockIds::BAMBOO_DOOR, 0, ItemIds::BAMBOO_DOOR));
		foreach (WoodType::values() as $type) {
			if ($type->equals(WoodType::OAK())) {
				continue;
			}

			$id = "minecraft:" . $type->getType() . "_door";
			self::registerItem(new ItemBlock($id, 0, $id));
		}
	}

	private static function registerShulkerBoxes() : void{
		foreach(ColorType::values() as $type){
			self::registerItem(new ShulkerBox($type));
		}
		self::registerItem(new ShulkerBox(new ColorType("undyed", "")));
	}

	private static function registerSpawnEggs() : void{
		self::registerItem(new SpawnEgg(ItemIds::BLAZE_SPAWN_EGG, 0, "Blaze Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::CAT_SPAWN_EGG, 0, "Cat Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::CAVE_SPIDER_SPAWN_EGG, 0, "Cave Spider Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::CHICKEN_SPAWN_EGG, 0, "Chicken Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::COD_SPAWN_EGG, 0, "Cod Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::COW_SPAWN_EGG, 0, "Cow Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::CREEPER_SPAWN_EGG, 0, "Creeper Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::ENDER_DRAGON_SPAWN_EGG, 0, "Ender Dragon Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::ENDERMAN_SPAWN_EGG, 0, "Enderman Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::HORSE_SPAWN_EGG, 0, "Horse Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::HUSK_SPAWN_EGG, 0, "Husk Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::MAGMA_CUBE_SPAWN_EGG, 0, "Magma Cube Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::MOOSHROOM_SPAWN_EGG, 0, "Mooshroom Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::PIG_SPAWN_EGG, 0, "Pig Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::SHEEP_SPAWN_EGG, 0, "Sheep Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::SKELETON_SPAWN_EGG, 0, "Skeleton Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::SLIME_SPAWN_EGG, 0, "Slime Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::VILLAGER_SPAWN_EGG, 0, "Villager Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::WOLF_SPAWN_EGG, 0, "Wolf Spawn Egg"));
		self::registerItem(new SpawnEgg(ItemIds::ZOMBIE_SPAWN_EGG, 0, "Zombie Spawn Egg"));
	}

	private static function registerSkulls() : void{
		self::registerItem(new ItemBlock(BlockIds::SKELETON_SKULL, 0 , BlockIds::SKELETON_SKULL));
		self::registerItem(new ItemBlock(BlockIds::WITHER_SKELETON_SKULL, 0 , BlockIds::WITHER_SKELETON_SKULL));
		self::registerItem(new ItemBlock(BlockIds::ZOMBIE_HEAD, 0 , BlockIds::ZOMBIE_HEAD));
		self::registerItem(new ItemBlock(BlockIds::PLAYER_HEAD, 0 , BlockIds::PLAYER_HEAD));
		self::registerItem(new ItemBlock(BlockIds::CREEPER_HEAD, 0 , BlockIds::CREEPER_HEAD));
		self::registerItem(new ItemBlock(BlockIds::DRAGON_HEAD, 0 , BlockIds::DRAGON_HEAD));
	}

	private static function registerMusicDiscs() : void{
		/** @var array<string, array{0:int, 1:string}> $records */
		$records = [
			ItemIds::MUSIC_DISC_13 => [Record::SOUND_RECORD_13, 'C418 - 13'],
			ItemIds::MUSIC_DISC_CAT => [Record::SOUND_RECORD_CAT, 'C418 - cat'],
			ItemIds::MUSIC_DISC_BLOCKS => [Record::SOUND_RECORD_BLOCKS, 'C418 - blocks'],
			ItemIds::MUSIC_DISC_CHIRP => [Record::SOUND_RECORD_CHIRP, 'C418 - chirp'],
			ItemIds::MUSIC_DISC_FAR => [Record::SOUND_RECORD_FAR, 'C418 - far'],
			ItemIds::MUSIC_DISC_MALL => [Record::SOUND_RECORD_MALL, 'C418 - mall'],
			ItemIds::MUSIC_DISC_MELLOHI => [Record::SOUND_RECORD_MELLOHI, 'C418 - mellohi'],
			ItemIds::MUSIC_DISC_STAL => [Record::SOUND_RECORD_STAL, 'C418 - stal'],
			ItemIds::MUSIC_DISC_STRAD => [Record::SOUND_RECORD_STRAD, 'C418 - strad'],
			ItemIds::MUSIC_DISC_WARD => [Record::SOUND_RECORD_WARD, 'C418 - ward'],
			ItemIds::MUSIC_DISC_11 => [Record::SOUND_RECORD_11, 'C418 - 11'],
			ItemIds::MUSIC_DISC_WAIT => [Record::SOUND_RECORD_WAIT, 'C418 - wait'],
			ItemIds::MUSIC_DISC_OTHERSIDE => [Record::SOUND_RECORD_OTHERSIDE, 'Lena Raine - otherside'],
			ItemIds::MUSIC_DISC_5 => [Record::SOUND_RECORD_5, 'Samuel Åberg - 5'],
			ItemIds::MUSIC_DISC_PIGSTEP => [Record::SOUND_RECORD_PIGSTEP, 'Lena Raine - Pigstep'],
			ItemIds::MUSIC_DISC_RELIC => [Record::SOUND_RECORD_RELIC, 'Aaron Cherof - relic'],
			ItemIds::MUSIC_DISC_CREATOR => [Record::SOUND_RECORD_CREATOR, 'Lena Raine - Creator'],
			ItemIds::MUSIC_DISC_CREATOR_MUSIC_BOX => [Record::SOUND_RECORD_CREATOR_MUSIC_BOX, 'Lena Raine - Creator (Music Box)'],
			ItemIds::MUSIC_DISC_PRECIPICE => [Record::SOUND_RECORD_PRECIPICE, 'Aaron Cherof - Precipice'],
			ItemIds::MUSIC_DISC_TEARS => [Record::SOUND_RECORD_TEARS, 'Amos Roddy - Tears'],
			ItemIds::MUSIC_DISC_LAVA_CHICKEN => [Record::SOUND_RECORD_LAVA_CHICKEN, 'Hyper Potions - Lava Chicken'],
			ItemIds::MUSIC_DISC_BOUNCE => [Record::SOUND_RECORD_BOUNCE, 'fingerspit - Bounce'],
		];

		foreach($records as $itemId => [$sound, $name]){
			self::registerItem(new Record($itemId, $sound, $name));
		}
	}

	private static function registerFenceGates() : void{
		foreach(WoodType::values() as $type){
			if ($type->equals(WoodType::OAK())) {
				self::registerItem(new ItemBlock(BlockIds::FENCE_GATE, 0, BlockIds::FENCE_GATE));
				continue;
			}

			self::registerItem(new ItemBlock("minecraft:" . $type->getType() . "_fence_gate", 0, "minecraft:" . $type->getType() . "_fence_gate"));
		}
		self::registerItem(new ItemBlock(BlockIds::BAMBOO_FENCE_GATE, 0, BlockIds::BAMBOO_FENCE_GATE));
	}


	private static function registerDyes() : void{
		foreach(ColorType::values() as $type){
			self::registerItem(new Dye($type));
		}
	}
}