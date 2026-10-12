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

namespace pocketmine\level\format;

use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\PalettedBlockArray;
use function assert;
use function chr;
use function define;
use function defined;
use function ord;
use function str_repeat;
use function strlen;
use function substr;
use function substr_count;

if(!defined(__NAMESPACE__ . '\ZERO_NIBBLE_ARRAY')){
	define(__NAMESPACE__ . '\ZERO_NIBBLE_ARRAY', str_repeat("\x00", 2048));
}

class SubChunk implements SubChunkInterface{
	private const ZERO_NIBBLE_ARRAY = ZERO_NIBBLE_ARRAY;
	public const MIN_LAYERS = 0;
	public const MAX_LAYERS = 3;

	/** @var string */
	protected $ids;
	/** @var string */
	protected $data;
	/** @var PalettedBlockArray[] */
	protected array $layers = [];
	protected int $emptyBlock;
	/** @var string */
	protected $blockLight;
	/** @var string */
	protected $skyLight;

	private static function assignData(string $data, int $length, string $value = "\x00") : string{
		if(strlen($data) !== $length){
			assert($data === "", "Invalid non-zero length given, expected $length, got " . strlen($data));
			return str_repeat($value, $length);
		}
		return $data;
	}

	public function __construct(int $emptyBlock, array $layers = [], string $skyLight = "", string $blockLight = ""){
		$this->emptyBlock = $emptyBlock;
		foreach($layers as $layer){
			if ($layer instanceof PalettedBlockArray) {
				$this->layers[] = $layer;
			}
		}

		$this->skyLight = self::assignData($skyLight, 2048, "\xff");
		$this->blockLight = self::assignData($blockLight, 2048);
		$this->collectGarbage();
	}

	public function isEmpty() : bool{
		return count($this->layers) === 0;
	}

	public function getBlockId(int $x, int $y, int $z, int $layer = 0) : int{
		return $this->getLayer($layer)->get($x, $y, $z);
	}

	public function setBlockId(int $x, int $y, int $z, int $id, int $layer = 0) : bool{
		$this->getLayer($layer)->set($x, $y, $z, $id);
		return true;
	}

	private function getLayer(int $layer, bool $generate = true) : PalettedBlockArray {
		if ($layer < self::MIN_LAYERS || $layer > self::MAX_LAYERS) {
			throw new \InvalidArgumentException("Invalid layer $layer, must be between " . self::MIN_LAYERS . " and " . self::MAX_LAYERS);
		}

		if (!isset($this->layers[$layer])) {
			if (!$generate) {
				throw new \InvalidArgumentException("Layer $layer does not exist");
			}

			$this->layers[$layer] = new PalettedBlockArray($this->emptyBlock);
		}

		return $this->layers[$layer];
	}

	public function getBlockLight(int $x, int $y, int $z) : int{
		return (ord($this->blockLight[($x << 7) | ($z << 3) | ($y >> 1)]) >> (($y & 1) << 2)) & 0xf;
	}

	public function setBlockLight(int $x, int $y, int $z, int $level) : bool{
		$i = ($x << 7) | ($z << 3) | ($y >> 1);

		$shift = ($y & 1) << 2;
		$byte = ord($this->blockLight[$i]);
		$this->blockLight[$i] = chr(($byte & ~(0xf << $shift)) | (($level & 0xf) << $shift));

		return true;
	}

	public function getBlockSkyLight(int $x, int $y, int $z) : int{
		return (ord($this->skyLight[($x << 7) | ($z << 3) | ($y >> 1)]) >> (($y & 1) << 2)) & 0xf;
	}

	public function setBlockSkyLight(int $x, int $y, int $z, int $level) : bool{
		$i = ($x << 7) | ($z << 3) | ($y >> 1);

		$shift = ($y & 1) << 2;
		$byte = ord($this->skyLight[$i]);
		$this->skyLight[$i] = chr(($byte & ~(0xf << $shift)) | (($level & 0xf) << $shift));

		return true;
	}

	public function getHighestBlockAt(int $x, int $z) : ?int{
		for ($y = 15; $y >= 0; --$y) {
			if ($this->getBlockId($x, $y, $z, 0) !== 0) {
				return $y;
			}
		}

		return null; //highest block not in this subchunk
	}

	public function getBlockIdColumn(int $x, int $z) : string{
		return substr($this->ids, ($x << 8) | ($z << 4), 16);
	}

	public function getBlockDataColumn(int $x, int $z) : string{
		return substr($this->data, ($x << 7) | ($z << 3), 8);
	}

	public function getBlockLightColumn(int $x, int $z) : string{
		return substr($this->blockLight, ($x << 7) | ($z << 3), 8);
	}

	public function getBlockSkyLightColumn(int $x, int $z) : string{
		return substr($this->skyLight, ($x << 7) | ($z << 3), 8);
	}

	public function getBlockIdArray() : string{
		assert(strlen($this->ids) === 4096, "Wrong length of ID array, expecting 4096 bytes, got " . strlen($this->ids));
		return $this->ids;
	}

	public function getBlockDataArray() : string{
		assert(strlen($this->data) === 2048, "Wrong length of data array, expecting 2048 bytes, got " . strlen($this->data));
		return $this->data;
	}

	public function getBlockSkyLightArray() : string{
		assert(strlen($this->skyLight) === 2048, "Wrong length of skylight array, expecting 2048 bytes, got " . strlen($this->skyLight));
		return $this->skyLight;
	}

	public function setBlockSkyLightArray(string $data) : void{
		assert(strlen($data) === 2048, "Wrong length of skylight array, expecting 2048 bytes, got " . strlen($data));
		$this->skyLight = $data;
	}

	public function getBlockLightArray() : string{
		assert(strlen($this->blockLight) === 2048, "Wrong length of light array, expecting 2048 bytes, got " . strlen($this->blockLight));
		return $this->blockLight;
	}

	public function setBlockLightArray(string $data) : void{
		assert(strlen($data) === 2048, "Wrong length of light array, expecting 2048 bytes, got " . strlen($data));
		$this->blockLight = $data;
	}

	public function networkSerialize(NetworkBinaryStream $stream) : void{
		$stream->putByte(LevelDB::CURRENT_SUBCHUNK_VERSION);
		$stream->putByte(count($this->layers));
		foreach ($this->layers as $layer) {
			$bitsPerBlock = $layer->getBitsPerBlock();
			$stream->putByte($bitsPerBlock << 1 | 1);
			$stream->put($layer->getWordArray());

			$palette = $layer->getPalette();
			if ($bitsPerBlock !== 0) {
				$stream->putUnsignedVarInt(count($palette) << 1);
			}

			foreach ($palette as $id) {
				$stream->put(Binary::writeUnsignedVarInt($id << 1));
			}
		}
	}

	/**
	 * @return mixed[]
	 */
	public function __debugInfo(){
		return [];
	}

	public function collectGarbage() : void{
		/*
		 * This strange looking code is designed to exploit PHP's copy-on-write behaviour. Assigning will copy a
		 * reference to the const instead of duplicating the whole string. The string will only be duplicated when
		 * modified, which is perfect for this purpose.
		 */
		$cleanedLayers = [];
		foreach($this->layers as $layer){
			$layer->collectGarbage();

			if($layer->getBitsPerBlock() !== 0 || $layer->get(0, 0, 0) !== $this->emptyBlock){
				$cleanedLayers[] = $layer;
			}
		}
		$this->layers = $cleanedLayers;

		if($this->skyLight === self::ZERO_NIBBLE_ARRAY){
			$this->skyLight = self::ZERO_NIBBLE_ARRAY;
		}
		if($this->blockLight === self::ZERO_NIBBLE_ARRAY){
			$this->blockLight = self::ZERO_NIBBLE_ARRAY;
		}
	}

	public function fastSerialize(BinaryStream $stream, bool $lightPopulated) : void{
		$stream->putInt($this->emptyBlock);
		$stream->putByte(count($this->layers));
		foreach ($this->layers as $layer) {
			$wordArray = $layer->getWordArray();
			$palette = $layer->getPalette();

			$stream->putByte($layer->getBitsPerBlock());
			$stream->put($wordArray);
			$serialPalette = pack("L*", ...$palette);
			$stream->putInt(strlen($serialPalette));
			$stream->put($serialPalette);
		}

		if ($lightPopulated) {
			$stream->put($this->getBlockSkyLightArray() . $this->getBlockLightArray());
		}
	}

	public static function fastDeserialize(BinaryStream $stream, bool $lightPopulated) : SubChunkInterface{
		$emptyBlock = $stream->getInt();
		$layerCount = $stream->getByte();

		$layers = [];
		for ($i = 0; $i < $layerCount; ++$i) {
			$bitsPerBlock = $stream->getByte();
			$words = $stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));
			/** @var int[] $unpackedPalette */
			$unpackedPalette = unpack("L*", $stream->get($stream->getInt())); //unpack() will never fail here
			$palette = array_values($unpackedPalette);
			$layers[] = PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
		}

		$skyLight = $lightPopulated ? $stream->get(2048) : "";
		$blockLight = $lightPopulated ? $stream->get(2048) : "";
		return new SubChunk($emptyBlock, $layers, $skyLight, $blockLight);
	}

	public function diskSerialize(BinaryStream $stream) : void{
		$stream->putByte(LevelDB::CURRENT_SUBCHUNK_VERSION);
		$stream->putByte(count($this->layers));
		$knownStates = RuntimeBlockMapping::getBedrockKnownStates();
		$unknownBlock = $knownStates[RuntimeBlockMapping::UNKNOWN()];

		foreach($this->layers as $layer) {
			$stream->putByte($layer->getBitsPerBlock() << 1);
			$stream->put($layer->getWordArray());

			$palette = $layer->getPalette();
			if ($layer->getBitsPerBlock() !== 0) {
				$stream->putLInt(count($palette));
			}

			$tags = [];
			foreach($palette as $block) {
				$tags[] = $knownStates[$block] ?? $unknownBlock;
			}

			$nbt = new LittleEndianNBTStream();
			$stream->put($nbt->write($tags));
		}
	}
}
