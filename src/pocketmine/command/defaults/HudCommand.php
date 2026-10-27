<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandSelector;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\SetHudPacket;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\network\mcpe\protocol\types\hud\HudElement;
use pocketmine\network\mcpe\protocol\types\hud\HudVisibility;
use pocketmine\Player;
use function array_keys;
use function array_merge;
use function count;
use function strtolower;

class HudCommand extends VanillaCommand{

	/** @var array<string, int[]> */
	private const SINGLE_ELEMENT_MAP = [
		"air_bubbles" => [HudElement::AIR_BUBBLES],
		"armor" => [HudElement::ARMOR],
		"crosshair" => [HudElement::CROSSHAIR],
		"health" => [HudElement::HEALTH],
		"horse_health" => [HudElement::VEHICLE_HEALTH],
		"hotbar" => [HudElement::HOTBAR],
		"hunger" => [HudElement::FOOD],
		"item_text" => [HudElement::ITEM_TEXT_POPUP],
		"paperdoll" => [HudElement::PAPER_DOLL],
		"progress_bar" => [HudElement::XP],
		"status_effects" => [HudElement::STATUS_EFFECTS],
		"tooltips" => [HudElement::TOOLTIPS],
		"touch_controls" => [HudElement::TOUCH_CONTROLS],
	];

	/** @var array<string, int[]> */
	private array $elementMap;

	public function __construct(string $name){
		$this->elementMap = array_merge(self::SINGLE_ELEMENT_MAP, [
			"all" => array_merge(...array_values(self::SINGLE_ELEMENT_MAP))
		]);

		parent::__construct(
			$name,
			"Changes the visibility of HUD elements.",
			"/hud <target: target> <visible: HudVisibility> [hud_element: HudElement]",
			[],
			[
				[
					new CommandParameter("player", AvailableCommandsPacket::ARG_TYPE_TARGET, false),
					new CommandParameter("visible", AvailableCommandsPacket::ARG_TYPE_STRING, false, new CommandEnum("HudVisibility", ["hide", "reset"])),
					new CommandParameter("hud_element", AvailableCommandsPacket::ARG_TYPE_STRING, true, new CommandEnum("HudElement", array_keys($this->elementMap))),
				]
			]
		);
		$this->setPermission("altay.command.hud");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		/** @var Player[] $targets */
		$targets = CommandSelector::findTargets($sender, $args[0], Player::class);
		if(count($targets) === 0){
			$sender->sendMessage("§cNo targets matched selector.");
			return true;
		}

		$visibility = match (strtolower($args[1])) {
			"hide" => HudVisibility::HIDE,
			"reset" => HudVisibility::RESET,
			default => throw new InvalidCommandSyntaxException()
		};

		$elementKey = strtolower($args[2] ?? "all");

		if(!isset($this->elementMap[$elementKey])){
			throw new InvalidCommandSyntaxException();
		}

		$pk = new SetHudPacket();
		$pk->hudElements = $this->elementMap[$elementKey];
		$pk->visibility = $visibility;

		$sender->getServer()->broadcastPacket($targets, $pk);

		$sender->sendMessage("Hud command successfully executed");

		return true;
	}
}