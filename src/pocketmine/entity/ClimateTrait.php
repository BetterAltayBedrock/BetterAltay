<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\level\biome\Biome;
use pocketmine\nbt\tag\CompoundTag;

/**
 * @property CompoundTag $namedtag
 */
trait ClimateTrait{

	protected int $climateVariant = ClimateVariant::VARIANT_TEMPERATE;

	public function getClimateVariant() : int{
		return $this->climateVariant;
	}

	public function setClimateVariant(int $variant) : void{
		$this->climateVariant = $variant;
	}

	protected function initClimateNBT() : void{
		$this->climateVariant = $this->namedtag->getInt("ClimateVariant", ClimateVariant::VARIANT_TEMPERATE);
	}

	protected function saveClimateNBT() : void {
		$this->namedtag->setInt("ClimateVariant", $this->climateVariant);
	}

	public function setClimateVariantFromBiome(Biome $biome) : void{
		$temp = $biome->getTemperature();

		if($temp < 0.2){
			$variant = ClimateVariant::VARIANT_COLD;
		}elseif($temp >= 1.0){
			$variant = ClimateVariant::VARIANT_WARM;
		}else{
			$variant = ClimateVariant::VARIANT_TEMPERATE;
		}

		$this->setClimateVariant($variant);
	}
}