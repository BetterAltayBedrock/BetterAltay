<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\mapper;

use InvalidArgumentException;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeGroupEntry;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use function json_decode;
use function file_get_contents;
use function is_array;
use function count;
use const pocketmine\RESOURCE_PATH;

class CreativeItemMapper{
	use SingletonTrait;

	private bool $initialized = false;
	/** @var array CreativeGroupEntry[] */
	private array $groups = [];

	private int $nextIconIndex = 1;
	/** @var array CreativeItemEntry[] */
	private array $icons = [];

	public function initCreativeContent() : void{
		if($this->initialized)
			return;

		$data = json_decode(file_get_contents(RESOURCE_PATH . '/vanilla/creative_items.json'), true);

		$groups = $data["groups"];
		if(!is_array($groups) or !count($groups))
			throw new AssumptionFailedError("Missing required groups");

		foreach($groups as $group){
			$name = $group["name"];
			$categoryId = $group["creative_category"];
			$icon = $group["icon"];

			try{
				$iconValue = ItemFactory::fromStringSingle($icon["id"]);
			}catch(InvalidArgumentException $ignore){
				$iconValue = ItemFactory::fromStringSingle("minecraft:air");
			}

			$this->groups[] = new CreativeGroupEntry($categoryId, $name, $iconValue);
		}

		$items = $data["items"];
		if(!is_array($items) or !count($items))
			throw new AssumptionFailedError("Missing required items");

		foreach($items as $item){
			[$id, $meta] = ItemTranslator::getInstance()->fromStringId($item["id"]);
			$itemValue = ItemFactory::get($id, $meta);

			if(isset($item["damage"])){
				$itemValue->setDamage($item["damage"]);
			}elseif($item["id"] === "minecraft:brown_mushroom_block" || $item["id"] === "minecraft:red_mushroom_block"){
				$itemValue->setDamage(14);
			}

			if(isset($item["nbt_b64"])){
				$nbtBytes = base64_decode($item["nbt_b64"], true);
				$nbtSerializer = new LittleEndianNBTStream();
				$decodedNbt = $nbtSerializer->read($nbtBytes);

				if(!($decodedNbt instanceof CompoundTag)){
					throw new \UnexpectedValueException("Unexpected root tag type");
				}
				$itemValue->setNamedTag($decodedNbt);


			}
			if($itemValue->getName() !== "Unknown"){
				$this->icons[] = new CreativeItemEntry($this->getNextIconIndex(), $itemValue, $item["group_index"]);
			}
		}

		$this->initialized = true;
	}

	/**
	 * @return CreativeGroupEntry[]
	 */
	public function getGroups() : array{
		return $this->groups;
	}

	public function getNextIconIndex() : int{
		return $this->nextIconIndex++;
	}

	/**
	 * @return CreativeItemEntry[]
	 */
	public function getIcons() : array{
		return $this->icons;
	}

	public function removeIconByIndex(int $index) : void{
		unset($this->icons[$index]);
	}

	public function addIcon(CreativeItemEntry $entry) : void{
		$this->icons[] = $entry;
	}

	public function removeAllIcons() : void{
		$this->icons = [];
	}
}