<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;

final class FurnaceOptions{

	public function __construct(
		private int $leftFurnaceTab,
		private bool $filtering,
		private int $furnaceLayout
	){
	}

	public function getLeftFurnaceTab() : int{ return $this->leftFurnaceTab; }

	public function getFiltering() : bool{ return $this->filtering; }

	public function getFurnaceLayout() : int{ return $this->furnaceLayout; }

	public static function read(NetworkBinaryStream $in) : self{
		$leftFurnaceTab = $in->getVarInt();
		$filtering = $in->getBool();
		$furnaceLayout = $in->getVarInt();

		return new self(
			$leftFurnaceTab,
			$filtering,
			$furnaceLayout
		);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putVarInt($this->leftFurnaceTab);
		$out->putBool($this->filtering);
		$out->putVarInt($this->furnaceLayout);
	}
}