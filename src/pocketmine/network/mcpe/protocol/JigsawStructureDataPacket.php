<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\utils\AssumptionFailedError;
use function base64_decode;
use function file_get_contents;
use function json_decode;
use const pocketmine\RESOURCE_PATH;

class JigsawStructureDataPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::JIGSAW_STRUCTURE_DATA_PACKET;

	private CompoundTag $data;
	/** @var JigsawStructureDataPacket|null */
	private static ?JigsawStructureDataPacket $cachedPacket = null;

	public static function create(CompoundTag $data) : self{
		$result = new self;
		$result->data = $data;
		return $result;
	}

	/**
	 * @return JigsawStructureDataPacket
	 */
	public static function fromJson() : JigsawStructureDataPacket{
		if(self::$cachedPacket !== null){
			return self::$cachedPacket;
		}

		$content = file_get_contents(RESOURCE_PATH . '/vanilla/jigsaw_structures.json');
		$jsonData = $content !== false ? json_decode($content, true) : null;

		if(!isset($jsonData['nbtB64'])){
			throw new AssumptionFailedError("Invalid resource file format");
		}

		$nbtStream = new NetworkLittleEndianNBTStream();

		$compound = $nbtStream->read(base64_decode($jsonData['nbtB64'], true));
		if($compound instanceof CompoundTag){
			return self::$cachedPacket = self::create($compound);
		}
		throw new AssumptionFailedError("No valid NBT entry found in jigsaw_structures.json");
	}

	public function getData() : CompoundTag{ return $this->data; }

	protected function decodePayload() : void{
		$this->data = $this->getNbtCompoundRoot();
	}

	protected function encodePayload() : void{
		$this->put((new NetworkLittleEndianNBTStream())->write($this->data));
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleJigsawStructureData($this);
	}
}
