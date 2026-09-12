<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\utils\UUID;

final class GatheringJoinInfo{

	public function __construct(
		private ?UUID $experienceId,
		private ?string $experienceName,
		private ?UUID $experienceWorldId,
		private ?string $experienceWorldName,
		private ?string $creatorId,
		private ?UUID $targetId,
		private ?string $scenarioId,
		private ?string $serverId,
	){
	}

	public function getExperienceId() : ?UUID{ return $this->experienceId; }

	public function getExperienceName() : ?string{ return $this->experienceName; }

	public function getExperienceWorldId() : ?UUID{ return $this->experienceWorldId; }

	public function getExperienceWorldName() : ?string{ return $this->experienceWorldName; }

	public function getCreatorId() : ?string{ return $this->creatorId; }

	public function getTargetId() : ?UUID{ return $this->targetId; }

	public function getScenarioId() : ?string{ return $this->scenarioId; }

	public function getServerId() : ?string{ return $this->serverId; }

	public static function read(NetworkBinaryStream $in) : self{
		$experienceId = $in->readOptional(fn() => $in->getUUID());
		$experienceName = $in->readOptional(fn() => $in->getString());
		$experienceWorldId = $in->readOptional(fn() => $in->getUUID());
		$experienceWorldName = $in->readOptional(fn() => $in->getString());
		$creatorId = $in->readOptional(fn() => $in->getString());
		$targetId = $in->readOptional(fn() => $in->getUUID());
		$scenarioId = $in->readOptional(fn() => $in->getString());
		$serverId = $in->readOptional(fn() => $in->getString());

		return new self(
			$experienceId,
			$experienceName,
			$experienceWorldId,
			$experienceWorldName,
			$creatorId,
			$targetId,
			$scenarioId,
			$serverId,
		);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->writeOptional($this->experienceId, fn($experienceId) => $out->putUUID($experienceId));
		$out->writeOptional($this->experienceName, fn($experienceName) => $out->putString($experienceName));
		$out->writeOptional($this->experienceWorldId, fn($experienceWorldId) => $out->putUUID($experienceWorldId));
		$out->writeOptional($this->experienceWorldName, fn($experienceWorldName) => $out->putString($experienceWorldName));
		$out->writeOptional($this->creatorId, fn($creatorId) => $out->putString($creatorId));
		$out->writeOptional($this->targetId, fn($targetId) => $out->putUUID($targetId));
		$out->writeOptional($this->scenarioId, fn($scenarioId) => $out->putString($scenarioId));
		$out->writeOptional($this->serverId, fn($serverId) => $out->putString($serverId));
	}
}