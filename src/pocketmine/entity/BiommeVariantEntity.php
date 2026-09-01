<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\level\biome\Biome;

interface BiommeVariantEntity{

	public function setVariantFromBiome(Biome $biome) : void;
}