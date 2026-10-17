<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\entity\passive;

use InvalidArgumentException;
use pocketmine\entity\Ageable;
use pocketmine\entity\behavior\AvoidMobTypeBehavior;
use pocketmine\entity\behavior\FloatBehavior;
use pocketmine\entity\behavior\LookAtPlayerBehavior;
use pocketmine\entity\behavior\LookAtTradingPlayerBehavior;
use pocketmine\entity\behavior\RandomLookAroundBehavior;
use pocketmine\entity\behavior\RandomStrollBehavior;
use pocketmine\entity\behavior\StayWhileTradingBehavior;
use pocketmine\entity\hostile\Zombie;
use pocketmine\entity\Mob;
use pocketmine\entity\NPC;
use pocketmine\entity\trade\TradeItem;
use pocketmine\entity\trade\TradeOffer;
use pocketmine\inventory\TradeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;

class Villager extends Mob implements NPC, Ageable{

	public const NETWORK_ID = self::VILLAGER;

	/** @var array<int, array<int, string>> */
	public static $names = [
		Villager::PROFESSION_FARMER => [
			Villager::CAREER_FARMER => "entity.villager.farmer",
			Villager::CAREER_FISHERMAN => "entity.villager.fisherman",
			Villager::CAREER_STEPHERD => "entity.villager.shepherd",
			Villager::CAREER_FLETCHER => "entity.villager.fletcher"
		], Villager::PROFESSION_LIBRARIAN => [
			Villager::CAREER_LIBRARIAN => "entity.villager.librarian",
			Villager::CAREER_CARTOGRAPHER => "entity.villager.cartographer"
		], Villager::PROFESSION_PRIEST => [
			Villager::CAREER_CLERIC => "entity.villager.cleric"
		], Villager::PROFESSION_BLACKSMITH => [
			Villager::CAREER_ARMOR => "entity.villager.armor", Villager::CAREER_WEAPON => "entity.villager.weapon",
			Villager::CAREER_TOOL => "entity.villager.tool"
		], Villager::PROFESSION_BUTCHER => [
			Villager::CAREER_BUTCHER => "entity.villager.butcher", Villager::CAREER_LEATHER => "entity.villager.leather"
		]
	];

	public const CAREER_FARMER = 1, CAREER_LIBRARIAN = 1, CAREER_CLERIC = 1, CAREER_ARMOR = 1, CAREER_BUTCHER = 1;
	public const CAREER_FISHERMAN = 2, CAREER_CARTOGRAPHER = 2, CAREER_WEAPON = 2, CAREER_LEATHER = 2;
	public const CAREER_STEPHERD = 3, CAREER_TOOL = 3;
	public const CAREER_FLETCHER = 4;

	public const PROFESSION_FARMER = 0;
	public const PROFESSION_LIBRARIAN = 1;
	public const PROFESSION_PRIEST = 2;
	public const PROFESSION_BLACKSMITH = 3;
	public const PROFESSION_BUTCHER = 4;

	public const TIER_EXP_REQUIREMENTS = [0, 10, 70, 150, 250];

	public $width = 0.6;
	public $height = 1.8;

	/** @var int */
	protected $career = self::CAREER_FARMER;
	/** @var int */
	protected $tradeTier = 0;
	/** @var int */
	protected $tradeExp = 0;
	/** @var bool */
	protected $isWilling = true;

	/** @var TradeOffer[] */
	protected $offers = [];

	public function getName() : string{
		return "Villager";
	}

	protected function addBehaviors() : void{
		$this->behaviorPool->setBehavior(0, new FloatBehavior($this));
		$this->behaviorPool->setBehavior(1, new StayWhileTradingBehavior($this));
		$this->behaviorPool->setBehavior(2, new AvoidMobTypeBehavior($this, Zombie::class, null, 8.0, 1.0, 1.2));
		$this->behaviorPool->setBehavior(3, new RandomStrollBehavior($this, 1.0));
		$this->behaviorPool->setBehavior(4, new LookAtTradingPlayerBehavior($this));
		$this->behaviorPool->setBehavior(5, new LookAtPlayerBehavior($this, 8.0));
		$this->behaviorPool->setBehavior(6, new RandomLookAroundBehavior($this));
	}

	protected function initEntity() : void{
		$this->setMaxHealth(20);
		$this->setMovementSpeed(0.25);
		$this->setFollowRange(16);

		parent::initEntity();

		if(!$this->namedtag->hasTag("Immobile") && !$this->namedtag->hasTag("NoAI")){
			$this->setImmobile(false);
		}

		$profession = $this->namedtag->getInt("Profession", self::PROFESSION_FARMER);
		if($profession > self::PROFESSION_BUTCHER || $profession < self::PROFESSION_FARMER){
			$profession = self::PROFESSION_FARMER;
		}
		$this->setProfession($profession);

		$career = $this->namedtag->getInt("Career", -1);
		if(!isset(self::$names[$this->getProfession()][$career])){
			$career = (int) array_rand(self::$names[$this->getProfession()]);
		}
		$this->career = $career;

		$this->tradeTier = max(0, $this->namedtag->getInt("TradeTier", 0));
		$this->tradeExp = max(0, $this->namedtag->getInt("TradeExp", 0));
		$this->loadOffers();
	}

	public function canDespawn() : bool{
		return false;
	}

	public function close() : void{
		$trader = $this->getTradingPlayer();
		if($trader !== null){
			$window = $trader->findWindow(TradeInventory::class);
			if($window instanceof TradeInventory && $window->getVillager() === $this){
				$trader->removeWindow($window);
			}
		}

		parent::close();
	}

	public function onUpdate(int $currentTick) : bool{
		$this->enforceTradingRange();
		return parent::onUpdate($currentTick);
	}

	private function enforceTradingRange() : void{
		$trader = $this->getTradingPlayer();
		if($trader === null){
			return;
		}

		if(!$this->isAlive() || $trader->isClosed() || !$trader->isAlive() || !$trader->spawned || $this->distance($trader) > 8){
			$window = $trader->findWindow(TradeInventory::class);
			if($window !== null){
				$trader->removeWindow($window);
			}
			$this->setTradingPlayer(null);
		}
	}

	/** @param array<string, mixed>[] $specs */
	public function setRecipes(array $specs) : void{
		$offers = [];
		foreach($specs as $spec){
			$offers[] = self::offerFromSpec($spec);
		}

		$this->offers = $offers;
	}

	/** @param array<string, mixed> $spec */
	public function addRecipe(array $spec) : void{
		$this->offers[] = self::offerFromSpec($spec);
	}

	public function clearRecipes() : void{
		$this->offers = [];
	}

	/** @return TradeOffer[] */
	public function getRecipes() : array{
		return $this->offers;
	}

	public function findOffer(int $netId) : ?TradeOffer{
		foreach($this->offers as $offer){
			if($offer->getNetId() === $netId){
				return $offer;
			}
		}

		return null;
	}

	public function findSatisfiedOffer(TradeInventory $inventory) : ?TradeOffer{
		[$inputA, $inputB] = self::readInputs($inventory);
		foreach($this->offers as $offer){
			if($offer->matchesInputs($inputA, $inputB, 1)){
				return $offer;
			}
		}

		return null;
	}

	public function executeTrade(Player $player, TradeInventory $inventory, int $netId, int $crafts) : ?Item{
		$offer = $this->findOffer($netId);
		if($offer === null){
			$offer = $this->findSatisfiedOffer($inventory);
			if($offer === null){
				return null;
			}
			$player->getServer()->getLogger()->debug("Trade craft from " . $player->getName() . " used unknown recipe " . $netId . ", falling back to " . $offer->getNetId());
		}

		if($crafts < 1){
			return null;
		}

		[$inputA, $inputB] = self::readInputs($inventory);
		if(!$offer->matchesInputs($inputA, $inputB, $crafts)){
			return null;
		}

		foreach([$offer->getBuyA(), $offer->getBuyB(), $offer->getSell()] as $tradeItem){
			if($tradeItem !== null && !self::isNetworkMappable($tradeItem)){
				$player->getServer()->getLogger()->error("Trade with " . $this->getDisplayName() . " rejected: item " . $tradeItem->getId() . ":" . $tradeItem->getMeta() . " has no Bedrock network mapping");
				return null;
			}
		}

		if(!$player->isCreative()){
			$this->takeInput($inventory, TradeInventory::SLOT_INPUT_A, $offer->getBuyA()->getCount() * $crafts);
			if($offer->getBuyB() !== null){
				$this->takeInput($inventory, TradeInventory::SLOT_INPUT_B, $offer->getBuyB()->getCount() * $crafts);
			}
		}

		$result = $offer->getSell()->toItem($crafts);
		$player->getUIInventory()->setItem(UIInventorySlotOffset::CREATED_ITEM_OUTPUT, $result);

		if(!$player->isCreative()){
			$offer->addUses($crafts);
			$this->tradeExp += $offer->getTraderExp() * $crafts;
			$this->bumpTier();
		}

		return $result;
	}

	public function executeLegacyTrade(Player $player, TradeInventory $inventory) : ?Item{
		$offer = $this->findSatisfiedOffer($inventory);
		if($offer === null){
			return null;
		}

		foreach([$offer->getBuyA(), $offer->getBuyB(), $offer->getSell()] as $tradeItem){
			if($tradeItem !== null && !self::isNetworkMappable($tradeItem)){
				$player->getServer()->getLogger()->error("Trade with " . $this->getDisplayName() . " rejected: item " . $tradeItem->getId() . ":" . $tradeItem->getMeta() . " has no Bedrock network mapping");
				return null;
			}
		}

		if(!$player->isCreative()){
			$this->takeInput($inventory, TradeInventory::SLOT_INPUT_A, $offer->getBuyA()->getCount());
			if($offer->getBuyB() !== null){
				$this->takeInput($inventory, TradeInventory::SLOT_INPUT_B, $offer->getBuyB()->getCount());
			}
			$offer->addUses(1);
			$this->tradeExp += $offer->getTraderExp();
			$this->bumpTier();
		}

		$inventory->refreshPreview();
		return $offer->getSell()->toItem();
	}

	public function openTrade(Player $player) : void{
		$old = $player->findWindow(TradeInventory::class);
		if($old !== null){
			$player->removeWindow($old);
		}

		$player->addWindow(new TradeInventory($this));
		$this->setTradingPlayer($player);
	}

	public function getOffersTag() : CompoundTag{
		$recipes = [];
		foreach($this->offers as $offer){
			$recipes[] = $offer->toNbt();
		}

		$tiers = [];
		foreach(self::TIER_EXP_REQUIREMENTS as $index => $requirement){
			$tiers[] = new CompoundTag("", [new IntTag((string) $index, $requirement)]);
		}

		return new CompoundTag("Offers", [
			new ListTag("Recipes", $recipes),
			new ListTag("TierExpRequirements", $tiers)
		]);
	}

	/** @return array{Item, Item|null} */
	private static function readInputs(TradeInventory $inventory) : array{
		$inputA = $inventory->getItem(TradeInventory::SLOT_INPUT_A);
		$rawB = $inventory->getItem(TradeInventory::SLOT_INPUT_B);
		return [$inputA, $rawB->isNull() ? null : $rawB];
	}

	private function takeInput(TradeInventory $inventory, int $slot, int $count) : void{
		$item = $inventory->getItem($slot);
		$remaining = $item->getCount() - $count;
		$rest = $remaining > 0 ? (clone $item)->setCount($remaining) : ItemFactory::get(Item::AIR, 0, 0);
		$inventory->setItem($slot, $rest);
		foreach($inventory->getViewers() as $viewer){
			if(!$viewer instanceof Player){
				continue;
			}
			$ui = $viewer->getUIInventory();
			foreach($inventory->getUIOffsets() as $uiSlot => $windowSlot){
				if($windowSlot === $slot && $ui->getItem($uiSlot)->equalsExact($item)){
					$ui->setItem($uiSlot, $rest);
				}
			}
		}
	}

	private function bumpTier() : void{
		while($this->tradeTier + 1 < count(self::TIER_EXP_REQUIREMENTS) && $this->tradeExp >= self::TIER_EXP_REQUIREMENTS[$this->tradeTier + 1]){
			$this->tradeTier++;
		}
	}

	/** @param TradeItem|array<mixed, mixed> $value */
	private static function specToItem($value, string $field) : TradeItem{
		if($value instanceof TradeItem){
			$item = $value;
		}elseif(is_array($value) && isset($value[0], $value[1], $value[2])){
			$item = new TradeItem((int) $value[0], (int) $value[1], (int) $value[2]);
		}else{
			throw new InvalidArgumentException("Invalid '$field' trade spec, expected TradeItem or [id, meta, count]");
		}

		try{
			ItemTranslator::getInstance()->toNetworkId($item->getId(), $item->getMeta());
		}catch(\InvalidArgumentException $e){
			throw new InvalidArgumentException("Invalid '$field' trade item " . $item->getId() . ":" . $item->getMeta() . ": no Bedrock network mapping, it would crash clients on send", 0, $e);
		}
		return $item;
	}

	private static function isNetworkMappable(TradeItem $item) : bool{
		try{
			ItemTranslator::getInstance()->toNetworkId($item->getId(), $item->getMeta());
			return true;
		}catch(\InvalidArgumentException $e){
			return false;
		}
	}

	/** @param array<string, mixed> $spec */
	private static function offerFromSpec(array $spec) : TradeOffer{
		if(!isset($spec["buy_a"], $spec["sell"])){
			throw new InvalidArgumentException("Trade spec requires 'buy_a' and 'sell'");
		}

		$buyA = $spec["buy_a"];
		$sell = $spec["sell"];
		$buyB = $spec["buy_b"] ?? null;
		if((!is_array($buyA) && !$buyA instanceof TradeItem) || (!is_array($sell) && !$sell instanceof TradeItem) || ($buyB !== null && !is_array($buyB) && !$buyB instanceof TradeItem)){
			throw new InvalidArgumentException("Trade spec 'buy_a', 'sell' and 'buy_b' must be TradeItem or [id, meta, count]");
		}

		$maxUses = $spec["max_uses"] ?? 12;
		$unlimited = $spec["unlimited"] ?? false;
		$traderExp = $spec["trader_exp"] ?? 1;
		$rewardExp = $spec["reward_exp"] ?? 1;
		$tier = $spec["tier"] ?? 0;
		if(!is_int($maxUses) || !is_bool($unlimited) || !is_int($traderExp) || !is_int($rewardExp) || !is_int($tier)){
			throw new InvalidArgumentException("Trade spec 'max_uses', 'trader_exp', 'reward_exp' and 'tier' must be integers and 'unlimited' a boolean");
		}

		return new TradeOffer(
			self::specToItem($buyA, "buy_a"),
			self::specToItem($sell, "sell"),
			$buyB !== null ? self::specToItem($buyB, "buy_b") : null,
			$maxUses,
			$unlimited,
			$traderExp,
			$rewardExp,
			$tier
		);
	}

	private function loadOffers() : void{
		$this->offers = [];
		$tag = $this->namedtag->getCompoundTag("Offers");
		if($tag === null){
			return;
		}

		$recipes = $tag->getListTag("Recipes");
		if($recipes === null){
			return;
		}

		foreach($recipes->getAllValues() as $recipe){
			if($recipe instanceof CompoundTag){
				$offer = TradeOffer::fromNbt($recipe);
				if($offer !== null){
					$this->offers[] = $offer;
				}
			}
		}
	}

	public function saveNBT() : void{
		parent::saveNBT();

		$this->namedtag->setInt("Profession", $this->getProfession());
		$this->namedtag->setInt("Career", $this->career);
		$this->namedtag->setInt("TradeTier", $this->tradeTier);
		$this->namedtag->setInt("TradeExp", $this->tradeExp);
		$this->namedtag->setTag($this->getOffersTag(), true);
	}

	public function setProfession(int $profession) : void{
		if($profession > self::PROFESSION_BUTCHER || $profession < self::PROFESSION_FARMER){
			throw new InvalidArgumentException("Profession $profession does not exist");
		}
		$this->propertyManager->setInt(self::DATA_VARIANT, $profession);
	}

	public function getProfession() : int{
		return $this->propertyManager->getInt(self::DATA_VARIANT) ?? self::PROFESSION_FARMER;
	}

	public function getTradeTier() : int{
		return $this->tradeTier;
	}

	public function getTradeExp() : int{
		return $this->tradeExp;
	}

	public function getCareer() : int{
		return $this->career;
	}

	public function setCareer(int $career) : void{
		$pro = $this->getProfession();
		if(!isset(self::$names[$pro][$career])){
			throw new InvalidArgumentException("$career is not found on $pro profession.");
		}

		$this->career = $career;
	}

	public function isBaby() : bool{
		return $this->getGenericFlag(self::DATA_FLAG_BABY);
	}

	public function onInteract(Player $player, Item $item, Vector3 $clickPos) : bool{
		if(!$this->isBaby() && !$this->isImmobile() && count($this->offers) > 0){
			$this->openTrade($player);
			return true;
		}
		return parent::onInteract($player, $item, $clickPos);
	}

	public function getDisplayName() : string{
		return self::$names[$this->getProfession()][$this->getCareer()] ?? "entity.villager.name";
	}

	public function isWilling() : bool{
		return $this->isWilling;
	}

	public function setWilling(bool $isWilling) : void{
		$this->isWilling = $isWilling;
	}

	public function getTradingPlayer() : ?Player{
		$eid = $this->getTradingPlayerId();
		if($eid === null || $eid < 0){
			return null;
		}
		$player = $this->server->findEntity($eid);

		return $player instanceof Player ? $player : null;
	}

	public function setTradingPlayer(?Player $player) : void{
		if($player === null){
			$this->propertyManager->setLong(self::DATA_TRADING_PLAYER_EID, -1);
		}elseif($player->isClosed()){
			throw new InvalidArgumentException("Supplied trading player is garbage and cannot be used");
		}else{
			$this->propertyManager->setLong(self::DATA_TRADING_PLAYER_EID, $player->getId());
		}
	}

	public function getTradingPlayerId() : ?int{
		return $this->propertyManager->getLong(self::DATA_TRADING_PLAYER_EID);
	}
}
