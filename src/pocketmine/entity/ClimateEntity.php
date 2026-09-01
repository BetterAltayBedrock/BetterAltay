<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\level\biome\Biome;

interface ClimateEntity{
	public function getClimateVariant() : int;

	public function setClimateVariant(int $variant) : void;

	public function setClimateVariantFromBiome(Biome $biome) : void;
}