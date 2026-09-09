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

use pocketmine\network\mcpe\NetworkSession;
use function count;

class LevelChunkPacket extends DataPacket/* implements ClientboundPacket*/
{
	public const NETWORK_ID = ProtocolInfo::LEVEL_CHUNK_PACKET;

	private const MAX_BLOB_HASHES = 64;

	private int $chunkX;
	private int $chunkZ;
	private int $dimensionId;
	private int $subChunkCount;
	private ?int $clientRequestSubChunkLimit = null;
	private bool $cacheEnabled;
	/** @var int[] */
	private array $usedBlobHashes = [];
	private string $extraPayload;
	private bool $isClientBiomeUpdate = false;

	/**
	 * @generate-create-func
	 *
	 * @param int[] $usedBlobHashes
	 */
	public static function create(
		int $chunkX,
		int $chunkZ,
		int $dimensionId,
		int $subChunkCount,
		?int $clientRequestSubChunkLimit,
		bool $cacheEnabled,
		array $usedBlobHashes,
		string $extraPayload,
		bool $isClientBiomeUpdate,
	): self{
		$result = new self;
		$result->chunkX = $chunkX;
		$result->chunkZ = $chunkZ;
		$result->dimensionId = $dimensionId;
		$result->subChunkCount = $subChunkCount;
		$result->clientRequestSubChunkLimit = $clientRequestSubChunkLimit;
		$result->cacheEnabled = $cacheEnabled;
		$result->usedBlobHashes = $usedBlobHashes;
		$result->extraPayload = $extraPayload;
		$result->isClientBiomeUpdate = $isClientBiomeUpdate;

		return $result;
	}

	public function getChunkX() : int{
		return $this->chunkX;
	}

	public function getChunkZ() : int{
		return $this->chunkZ;
	}

	public function getDimensionId() : int{
		return $this->dimensionId;
	}

	public function getSubChunkCount() : int{
		return $this->subChunkCount;
	}

	public function isClientSubChunkRequestEnabled() : bool{
		return $this->clientRequestSubChunkLimit !== null;
	}

	public function getClientRequestSubChunkLimit() : ?int{
		return $this->clientRequestSubChunkLimit;
	}

	public function isCacheEnabled() : bool{
		return $this->cacheEnabled;
	}

	/**
	 * @return int[]
	 */
	public function getUsedBlobHashes() : array{
		return $this->usedBlobHashes;
	}

	public function getExtraPayload() : string{
		return $this->extraPayload;
	}

	public function isClientBiomeUpdate() : bool{
		return $this->isClientBiomeUpdate;
	}

	protected function decodePayload() : void{
		$this->chunkX = $this->getVarInt();
		$this->chunkZ = $this->getVarInt();
		$this->dimensionId = $this->getVarInt();
		$this->subChunkCount = $this->getUnsignedVarInt();

		$this->clientRequestSubChunkLimit = $this->getBool() ? $this->getVarInt() : null;
		$this->cacheEnabled = $this->getBool();

		$this->usedBlobHashes = [];
		$count = $this->getUnsignedVarInt();
		if($count > self::MAX_BLOB_HASHES){
			throw new \Exception("Expected at most " . self::MAX_BLOB_HASHES . " blob hashes, got " . $count);
		}
		for($i = 0; $i < $count; ++$i){
			$this->usedBlobHashes[] = $this->getLLong();
		}

		$this->extraPayload = $this->getString();
		$this->isClientBiomeUpdate = $this->getBool();
	}

	protected function encodePayload() : void{
		$this->putVarInt($this->chunkX);
		$this->putVarInt($this->chunkZ);
		$this->putVarInt($this->dimensionId);
		$this->putUnsignedVarInt($this->subChunkCount);

		$this->putBool($this->clientRequestSubChunkLimit !== null);
		if($this->clientRequestSubChunkLimit !== null){
			$this->putVarInt($this->clientRequestSubChunkLimit);
		}
		$this->putBool($this->cacheEnabled);

		$this->putUnsignedVarInt(count($this->usedBlobHashes));
		foreach($this->usedBlobHashes as $hash){
			$this->putLLong($hash);
		}

		$this->putString($this->extraPayload);
		$this->putBool($this->isClientBiomeUpdate);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleLevelChunk($this);
	}
}
