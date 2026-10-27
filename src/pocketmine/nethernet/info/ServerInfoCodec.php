<?php

/*
 * This file is part of NetherNet.
 * Copyright (C) 2026 Axolotl Team <https://github.com/axolotl-pm/NetherNet>
 *
 * NetherNet is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\nethernet\info;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\nethernet\signaling\lan\packet\DiscoveryException;
use function json_encode;
use function strlen;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Binary (LAN advertisement) and JSON (HTTP probe) encodings of a {@link ServerInfo}.
 */
final class ServerInfoCodec{

	private function __construct(){
		//NOOP
	}

	public static function encode(ServerInfo $info) : string{
		$out = new ByteBufferWriter();

		Byte::writeUnsigned($out, ServerInfo::DATA_VERSION);
		self::writeString($out, $info->serverName);
		VarInt::writeSignedInt($out, $info->protocol);
		self::writeString($out, $info->gameVersion);
		self::writeString($out, $info->levelName);
		VarInt::writeSignedInt($out, $info->playerCount);
		VarInt::writeSignedInt($out, $info->maxPlayerCount);
		VarInt::writeSignedInt($out, $info->gameType->value);
		Byte::writeUnsigned($out, $info->editorWorld ? 1 : 0);
		Byte::writeUnsigned($out, $info->hardcore ? 1 : 0);
		Byte::writeUnsigned($out, $info->acceptsOnlineAuth ? 1 : 0);
		Byte::writeUnsigned($out, $info->acceptsSelfSignedAuth ? 1 : 0);
		self::writeString($out, $info->nonce);
		VarInt::writeSignedInt($out, ServerInfo::CONNECTION_TYPE_NETHERNET);

		return $out->getData();
	}

	/**
	 * @throws \JsonException
	 */
	public static function encodeJson(ServerInfo $info) : string{
		return json_encode([
			"dataVersion" => ServerInfo::DATA_VERSION,
			"name" => $info->serverName,
			"protocol" => $info->protocol,
			"version" => $info->gameVersion,
			"level" => $info->levelName,
			"players" => $info->playerCount,
			"maxPlayers" => $info->maxPlayerCount,
			"gameType" => $info->gameType->value,
			"editor" => $info->editorWorld,
			"hardcore" => $info->hardcore,
			"onlineAuth" => $info->acceptsOnlineAuth,
			"selfSignedAuth" => $info->acceptsSelfSignedAuth,
			"nonce" => $info->nonce,
			"transportLayer" => ServerInfo::TRANSPORT_LAYER_NETHERNET,
			"connection" => ServerInfo::CONNECTION_TYPE_NETHERNET
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @throws DiscoveryException
	 */
	public static function decode(string $data) : ServerInfo{
		$in = new ByteBufferReader($data);

		try{
			$version = Byte::readUnsigned($in);
			if($version !== ServerInfo::DATA_VERSION){
				throw new DiscoveryException("Expected server advertisement version " . ServerInfo::DATA_VERSION . ", got $version");
			}

			$serverName = self::readString($in);
			$protocol = VarInt::readSignedInt($in);
			$gameVersion = self::readString($in);
			$levelName = self::readString($in);
			$playerCount = VarInt::readSignedInt($in);
			$maxPlayerCount = VarInt::readSignedInt($in);

			$rawGameType = VarInt::readSignedInt($in);
			$gameType = GameType::tryFrom($rawGameType);
			if($gameType === null){
				throw new DiscoveryException("Unknown game type $rawGameType");
			}

			$editorWorld = Byte::readUnsigned($in) !== 0;
			$hardcore = Byte::readUnsigned($in) !== 0;
			$acceptsOnlineAuth = Byte::readUnsigned($in) !== 0;
			$acceptsSelfSignedAuth = Byte::readUnsigned($in) !== 0;
			$nonce = self::readString($in);

			$connectionType = VarInt::readSignedInt($in);
			if($connectionType !== ServerInfo::CONNECTION_TYPE_NETHERNET){
				throw new DiscoveryException("Expected connection type " . ServerInfo::CONNECTION_TYPE_NETHERNET . ", got $connectionType");
			}

			if($in->getUnreadLength() !== 0){
				throw new DiscoveryException("Server advertisement has " . $in->getUnreadLength() . " unread bytes remaining");
			}
		}catch(DataDecodeException $e){
			throw new DiscoveryException("Malformed server advertisement: " . $e->getMessage(), 0, $e);
		}

		return new ServerInfo(
			$serverName,
			$protocol,
			$gameVersion,
			$levelName,
			$gameType,
			$playerCount,
			$maxPlayerCount,
			$editorWorld,
			$hardcore,
			$acceptsOnlineAuth,
			$acceptsSelfSignedAuth,
			$nonce
		);
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function readString(ByteBufferReader $in) : string{
		return $in->readByteArray(VarInt::readUnsignedInt($in));
	}

	private static function writeString(ByteBufferWriter $out, string $value) : void{
		VarInt::writeUnsignedInt($out, strlen($value));
		$out->writeByteArray($value);
	}
}
