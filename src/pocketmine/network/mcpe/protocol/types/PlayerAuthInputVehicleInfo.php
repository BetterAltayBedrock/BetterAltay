<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;

final class PlayerAuthInputVehicleInfo{

	public function __construct(
		private ?float $vehicleRotationX = null,
		private ?float $vehicleRotationZ = null,
		private ?int $predictedVehicleActorUniqueId = null
	){}


	public function getVehicleRotationX() : ?float{ return $this->vehicleRotationX; }

	public function getVehicleRotationZ() : ?float{ return $this->vehicleRotationZ; }

	public function getPredictedVehicleActorUniqueId() : ?int{ return $this->predictedVehicleActorUniqueId; }

	public static function read(NetworkBinaryStream $in) : self{
		$self = new self();

		if($in->getBool()){
			$self->vehicleRotationX = $in->getLFloat();
			$self->vehicleRotationZ = $in->getLFloat();
		}

		if($in->getBool()){
			$self->predictedVehicleActorUniqueId = $in->getEntityUniqueId();
		}

		return $self;
	}

	public function write(NetworkBinaryStream $out) : void{

		if ($this->vehicleRotationX !== null && $this->vehicleRotationZ !== null) {
			$out->putBool(true);
			$out->putLFloat($this->vehicleRotationX);
			$out->putLFloat($this->vehicleRotationZ);
		} else {
			$out->putBool(false);
		}

		if ($this->predictedVehicleActorUniqueId !== null) {
			$out->putBool(true);
			$out->putEntityUniqueId($this->predictedVehicleActorUniqueId);
		} else {
			$out->putBool(false);
		}
	}

	public function isNull(): bool{
		return $this->vehicleRotationX === null && $this->vehicleRotationZ === null && $this->predictedVehicleActorUniqueId === null;
	}
}
