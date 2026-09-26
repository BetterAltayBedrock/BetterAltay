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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\EducationEditionOffer;
use pocketmine\network\mcpe\protocol\types\EducationUriResource;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\GameRuleType;
use pocketmine\network\mcpe\protocol\types\GeneratorType;
use pocketmine\network\mcpe\protocol\types\MultiplayerGameVisibility;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\utils\UUID;
use function count;

class StartGamePacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::START_GAME_PACKET;

	public int $entityUniqueId;
	public int $entityRuntimeId;
	public int $playerGamemode;

	public Vector3 $playerPosition;

	public float $pitch;
	public float $yaw;

	public int $seed;
	public SpawnSettings $spawnSettings;
	public int $generator = GeneratorType::OVERWORLD;
	public int $worldGamemode;
	public bool $hardcoreMode = false;
	public int $difficulty;
	public int $spawnX;
	public int $spawnY;
	public int $spawnZ;
	public bool $hasAchievementsDisabled = true;
	public int $editorWorldType = 0; //non editor
	public bool $createdInEditor = false;
	public bool $exportedFromEditor = false;
	public int $editorLevelMigrationVersion = 0; //legacy
	public int $time = -1;
	public int $eduEditionOffer = EducationEditionOffer::NONE;
	public bool $hasEduFeaturesEnabled = false;
	public string $eduProductUUID = "";
	public float $rainLevel;
	public float $lightningLevel;
	public bool $hasConfirmedPlatformLockedContent = false;
	public bool $isMultiplayerGame = true;
	public bool $hasLANBroadcast = true;
	public int $xboxLiveBroadcastMode = MultiplayerGameVisibility::PUBLIC;
	public int $platformBroadcastMode = MultiplayerGameVisibility::PUBLIC;
	public bool $commandsEnabled;
	public bool $isTexturePacksRequired = true;
	/**
	 * @var mixed[][]
	 * @phpstan-var array<string, array{0: int, 1: bool|int|float, 2: bool}>
	 */
	public array $gameRules = [ //TODO: implement this
		"naturalregeneration" => [GameRuleType::BOOL, false, false] //Hack for client side regeneration
	];
	public Experiments $experiments;
	public bool $hasBonusChestEnabled = false;
	public bool $hasStartWithMapEnabled = false;
	public int $defaultPlayerPermission = PlayerPermissions::MEMBER; //TODO

	public int $serverChunkTickRadius = 4; //TODO (leave as default for now)

	public bool $hasLockedBehaviorPack = false;
	public bool $hasLockedResourcePack = false;
	public bool $isFromLockedWorldTemplate = false;
	public bool $useMsaGamertagsOnly = false;
	public bool $isFromWorldTemplate = false;
	public bool $isWorldTemplateOptionLocked = false;
	public bool $onlySpawnV1Villagers = false;
	public bool $personaDisabled = false;
	public bool $customSkinsDisabled = false;
	public bool $emoteChatMuted = true; //Prevent spam
	public string $vanillaVersion = ProtocolInfo::MINECRAFT_VERSION_NETWORK;
	public int $limitedWorldWidth = 0;
	public int $limitedWorldLength = 0;
	public bool $isNewNether = true;
	public ?EducationUriResource $eduSharedUriResource = null;
	public ?bool $experimentalGameplayOverride = null;

	public int $chatRestrictionLevel = 0; //None
	public bool $disablePlayerInteractions = false;
	public int $serverEditorConnectionPolicy = 0;
	public bool $allowAnonymousBlockDropsInEditorWorlds = false;
	public string $levelId = ""; //base64 string, usually the same as world folder name in vanilla
	public string $worldName;
	public string $premiumWorldTemplateId = "";
	public bool $isTrial = false;
	public PlayerMovementSettings $playerMovementSettings;
	public int $currentTick = 0; //only used if isTrial is true
	public int $enchantmentSeed = 0;
	public string $multiplayerCorrelationId = ""; //TODO: this should be filled with a UUID of some sort

	/**
	 * @var BlockPaletteEntry[]
	 * @phpstan-var list<BlockPaletteEntry>
	 */
	public array $blockPalette = [];

	public bool $enableNewInventorySystem = false; //TODO
	public string $serverSoftwareVersion;
	public CompoundTag $propertyData;
	public int $blockPaletteChecksum;
	public UUID $worldTemplateId;
	public bool $clientSideGeneration = false;
	public bool $blockNetworkIdsAreHashes = false;
	public bool $serverAuthSound = true;
	public bool $serverJoinInformation = false;
	public string $serverId = "";
	public string $scenarioId = "";
	public string $worldId = "";
	public string $ownerId = "";

	protected function decodePayload() : void{
		$this->entityUniqueId = $this->getEntityUniqueId();
		$this->entityRuntimeId = $this->getEntityRuntimeId();
		$this->playerGamemode = $this->getVarInt();

		$this->playerPosition = $this->getVector3();

		$this->pitch = $this->getLFloat();
		$this->yaw = $this->getLFloat();

		//Level settings
		$this->seed = $this->getLLong();
		$this->spawnSettings = SpawnSettings::read($this);
		$this->generator = $this->getVarInt();
		$this->worldGamemode = $this->getVarInt();
		$this->hardcoreMode = $this->getBool();
		$this->difficulty = $this->getVarInt();
		$this->getBlockPosition($this->spawnX, $this->spawnY, $this->spawnZ);
		$this->hasAchievementsDisabled = $this->getBool();
		$this->editorWorldType = $this->getVarInt();
		$this->createdInEditor = $this->getBool();
		$this->exportedFromEditor = $this->getBool();
		$this->editorLevelMigrationVersion = $this->getByte();
		$this->time = $this->getVarInt();
		$this->eduEditionOffer = $this->getUnsignedVarInt();
		$this->hasEduFeaturesEnabled = $this->getBool();
		$this->eduProductUUID = $this->getString();
		$this->rainLevel = $this->getLFloat();
		$this->lightningLevel = $this->getLFloat();
		$this->hasConfirmedPlatformLockedContent = $this->getBool();
		$this->isMultiplayerGame = $this->getBool();
		$this->hasLANBroadcast = $this->getBool();
		$this->xboxLiveBroadcastMode = $this->getVarInt();
		$this->platformBroadcastMode = $this->getVarInt();
		$this->commandsEnabled = $this->getBool();
		$this->isTexturePacksRequired = $this->getBool();
		$this->gameRules = $this->getGameRules();
		$this->experiments = Experiments::read($this);
		$this->hasBonusChestEnabled = $this->getBool();
		$this->hasStartWithMapEnabled = $this->getBool();
		$this->defaultPlayerPermission = $this->getByte();
		$this->serverChunkTickRadius = $this->getLInt();
		$this->hasLockedBehaviorPack = $this->getBool();
		$this->hasLockedResourcePack = $this->getBool();
		$this->isFromLockedWorldTemplate = $this->getBool();
		$this->useMsaGamertagsOnly = $this->getBool();
		$this->isFromWorldTemplate = $this->getBool();
		$this->isWorldTemplateOptionLocked = $this->getBool();
		$this->onlySpawnV1Villagers = $this->getBool();
		$this->personaDisabled = $this->getBool();
		$this->customSkinsDisabled = $this->getBool();
		$this->emoteChatMuted = $this->getBool();
		$this->vanillaVersion = $this->getString();
		$this->limitedWorldWidth = $this->getLInt();
		$this->limitedWorldLength = $this->getLInt();
		$this->isNewNether = $this->getBool();
		$this->eduSharedUriResource = EducationUriResource::read($this);
		if($this->getBool()){
			$this->experimentalGameplayOverride = $this->getBool();
		}else{
			$this->experimentalGameplayOverride = null;
		}

		$this->chatRestrictionLevel = $this->getByte();
		$this->disablePlayerInteractions = $this->getBool();
		$this->serverEditorConnectionPolicy = $this->getVarInt();
		$this->allowAnonymousBlockDropsInEditorWorlds = $this->getBool();
		$this->levelId = $this->getString();
		$this->worldName = $this->getString();
		$this->premiumWorldTemplateId = $this->getString();
		$this->isTrial = $this->getBool();
		$this->playerMovementSettings = PlayerMovementSettings::read($this);
		$this->currentTick = $this->getLLong();

		$this->enchantmentSeed = $this->getVarInt();

		$this->blockPalette = [];
		for($i = 0, $len = $this->getUnsignedVarInt(); $i < $len; ++$i){
			$blockName = $this->getString();
			$state = $this->getNbtCompoundRoot();
			$this->blockPalette[] = new BlockPaletteEntry($blockName, $state);
		}

		$this->multiplayerCorrelationId = $this->getString();
		$this->enableNewInventorySystem = $this->getBool();
		$this->serverSoftwareVersion = $this->getString();
		$this->propertyData = $this->getNbtCompoundRoot();
		$this->blockPaletteChecksum = $this->getLLong();
		$this->worldTemplateId = $this->getUUID();
		$this->clientSideGeneration = $this->getBool();
		$this->blockNetworkIdsAreHashes = $this->getBool();
		$this->serverAuthSound = $this->getBool();
		$this->serverJoinInformation = $this->getBool();
		if($this->serverJoinInformation){
			$this->getBool();
		}
		$this->serverId = $this->getString();
		$this->scenarioId = $this->getString();
		$this->worldId = $this->getString();
		$this->ownerId = $this->getString();
	}

	protected function encodePayload() : void{
		$this->putEntityUniqueId($this->entityUniqueId);
		$this->putEntityRuntimeId($this->entityRuntimeId);
		$this->putVarInt($this->playerGamemode);

		$this->putVector3($this->playerPosition);

		$this->putLFloat($this->pitch);
		$this->putLFloat($this->yaw);

		//Level settings
		$this->putLLong($this->seed);
		$this->spawnSettings->write($this);
		$this->putVarInt($this->generator);
		$this->putVarInt($this->worldGamemode);
		$this->putBool($this->hardcoreMode);
		$this->putVarInt($this->difficulty);
		$this->putBlockPosition($this->spawnX, $this->spawnY, $this->spawnZ);
		$this->putBool($this->hasAchievementsDisabled);
		$this->putVarInt($this->editorWorldType);
		$this->putBool($this->createdInEditor);
		$this->putBool($this->exportedFromEditor);
		$this->putByte($this->editorLevelMigrationVersion);
		$this->putVarInt($this->time);
		$this->putUnsignedVarInt($this->eduEditionOffer);
		$this->putBool($this->hasEduFeaturesEnabled);
		$this->putString($this->eduProductUUID);
		$this->putLFloat($this->rainLevel);
		$this->putLFloat($this->lightningLevel);
		$this->putBool($this->hasConfirmedPlatformLockedContent);
		$this->putBool($this->isMultiplayerGame);
		$this->putBool($this->hasLANBroadcast);
		$this->putVarInt($this->xboxLiveBroadcastMode);
		$this->putVarInt($this->platformBroadcastMode);
		$this->putBool($this->commandsEnabled);
		$this->putBool($this->isTexturePacksRequired);
		$this->putGameRules($this->gameRules);
		$this->experiments->write($this);
		$this->putBool($this->hasBonusChestEnabled);
		$this->putBool($this->hasStartWithMapEnabled);
		$this->putByte($this->defaultPlayerPermission);
		$this->putLInt($this->serverChunkTickRadius);
		$this->putBool($this->hasLockedBehaviorPack);
		$this->putBool($this->hasLockedResourcePack);
		$this->putBool($this->isFromLockedWorldTemplate);
		$this->putBool($this->useMsaGamertagsOnly);
		$this->putBool($this->isFromWorldTemplate);
		$this->putBool($this->isWorldTemplateOptionLocked);
		$this->putBool($this->onlySpawnV1Villagers);
		$this->putBool($this->personaDisabled);
		$this->putBool($this->customSkinsDisabled);
		$this->putBool($this->emoteChatMuted);
		$this->putString($this->vanillaVersion);
		$this->putLInt($this->limitedWorldWidth);
		$this->putLInt($this->limitedWorldLength);
		$this->putBool($this->isNewNether);
		($this->eduSharedUriResource ?? new EducationUriResource("", ""))->write($this);
		$this->putBool($this->experimentalGameplayOverride !== null);
		if($this->experimentalGameplayOverride !== null){
			$this->putBool($this->experimentalGameplayOverride);
		}

		$this->putByte($this->chatRestrictionLevel);
		$this->putBool($this->disablePlayerInteractions);
		$this->putVarInt($this->serverEditorConnectionPolicy);
		$this->putBool($this->allowAnonymousBlockDropsInEditorWorlds);
		//Level settings end

		$this->putString($this->levelId);
		$this->putString($this->worldName);
		$this->putString($this->premiumWorldTemplateId);
		$this->putBool($this->isTrial);
		$this->playerMovementSettings->write($this);
		$this->putLLong($this->currentTick);

		$this->putVarInt($this->enchantmentSeed);

		$this->putUnsignedVarInt(count($this->blockPalette));
		$nbtWriter = new NetworkLittleEndianNBTStream();
		foreach($this->blockPalette as $entry){
			$this->putString($entry->getName());
			$this->put($nbtWriter->write($entry->getStates()));
		}

		$this->putString($this->multiplayerCorrelationId);
		$this->putBool($this->enableNewInventorySystem);
		$this->putString($this->serverSoftwareVersion);
		$this->put((new NetworkLittleEndianNBTStream())->write($this->propertyData));
		$this->putLLong($this->blockPaletteChecksum);
		$this->putUUID($this->worldTemplateId);
		$this->putBool($this->clientSideGeneration);
		$this->putBool($this->blockNetworkIdsAreHashes);
		$this->putBool($this->serverAuthSound);	//NetworkPermissions
		$this->putBool($this->serverJoinInformation = false); //make sure this is false for now as we don't have all fields implemented related to this yet
		//ServerTelemetryData
		$this->putString($this->serverId);
		$this->putString($this->scenarioId);
		$this->putString($this->worldId);
		$this->putString($this->ownerId);
		//ServerTelemetryData end
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleStartGame($this);
	}
}
