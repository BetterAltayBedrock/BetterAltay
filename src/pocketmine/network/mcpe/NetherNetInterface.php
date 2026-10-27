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

namespace pocketmine\network\mcpe;

use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\network\AdvancedSourceInterface;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\nethernet\identity\PeerIdentity;
use pocketmine\nethernet\identity\SelfSignedIdentityProvider;
use pocketmine\nethernet\identity\ServerIdentity;
use pocketmine\nethernet\info\GameType;
use pocketmine\nethernet\info\MutableServerInfoProvider;
use pocketmine\nethernet\info\ServerInfo;
use pocketmine\nethernet\protocol\DisconnectReason;
use pocketmine\nethernet\protocol\Reliability;
use pocketmine\nethernet\server\NetherNetServer;
use pocketmine\nethernet\server\ServerConfiguration;
use pocketmine\nethernet\server\ServerEventListener;
use pocketmine\nethernet\server\ServerState;
use pocketmine\nethernet\signaling\http\HttpSignaling;
use pocketmine\nethernet\signaling\lan\LanSignaling;
use pocketmine\nethernet\utils\InternetAddress;
use pocketmine\network\Network;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use Throwable;
use function base64_encode;
use function file_get_contents;
use function file_put_contents;
use function get_class;
use function is_file;
use function is_string;
use function microtime;
use function random_int;
use function spl_object_hash;
use function substr;
use function usleep;
use const PTHREADS_INHERIT_CONSTANTS;

/**
 * Bridges the server's network layer to the NetherNet (WebRTC DataChannel) transport used by console clients and by
 * join-by-address on any client with NetherNet support.
 *
 * The MCPE layer framing is the same as on the RakLib path, except that NetherNet messages carry the batch payload
 * without the leading 0xFE game-packet ID, so it is added back before handing packets to BatchPacket.
 */
class NetherNetInterface implements AdvancedSourceInterface, ServerEventListener{

	private const MCPE_RAKNET_PACKET_ID = "\xfe";

	/** Seconds to keep ticking during shutdown so disconnects can flush to clients. */
	private const SHUTDOWN_FLUSH_TIMEOUT = 3.0;

	/** How often the ticker thread wakes the main thread to tick the transport, in milliseconds. */
	private const TICK_INTERVAL_MS = 10;

	/** @var Server */
	private $server;
	/** @var Network|null */
	private $network = null;

	/** @var NetherNetServer */
	private $nethernet;
	/** @var MutableServerInfoProvider */
	private $infoProvider;
	/** @var SleeperNotifier */
	private $sleeper;
	/** @var NetherNetTickerThread|null */
	private $ticker = null;

	/** @var Player[] sessionId => player */
	private $players = [];
	/** @var int[] session id by player object hash */
	private $identifiers = [];
	/** @var int[] */
	private $identifiersACK = [];

	public function __construct(Server $server){
		$this->server = $server;
		$this->infoProvider = new MutableServerInfoProvider();
		$this->sleeper = new SleeperNotifier();

		$advertised = [];
		foreach((array) $this->server->getProperty("network.nethernet.advertised-addresses", []) as $address){
			if(is_string($address) && $address !== ""){
				$advertised[] = $address;
			}
		}

		$configuration = new ServerConfiguration(
			identityProvider: new SelfSignedIdentityProvider($this->loadOrCreateIdentity()),
			logger: $this->server->getLogger(),
			advertisedAddresses: $advertised !== [] ? $advertised : null
		);
		$this->nethernet = NetherNetServer::create($configuration, $this);

		$signalingPort = (int) $this->server->getProperty("network.nethernet.signaling-port", 0);
		if($signalingPort <= 0){
			$signalingPort = $this->server->getPort();
		}
		//clients joining by address send their SDP offer to the port they entered
		$this->nethernet->addSignaling(new HttpSignaling(
			new InternetAddress($this->server->getIp(), $signalingPort, 4),
			infoProvider: $this->infoProvider
		));

		if((bool) $this->server->getProperty("network.nethernet.lan-discovery", true)){
			$networkId = (int) $this->server->getProperty("network.nethernet.network-id", 0);
			if($networkId === 0){
				$networkId = random_int(1, PHP_INT_MAX);
			}
			$this->nethernet->addSignaling(new LanSignaling($this->infoProvider, $networkId), optional: true);
		}
	}

	/**
	 * Loads the persistent server identity used to sign the DTLS fingerprint, or generates a new one. A stable
	 * identity lets clients cache the server's public key between sessions.
	 */
	private function loadOrCreateIdentity() : ServerIdentity{
		$path = $this->server->getDataPath() . "nethernet-identity.pem";
		if(is_file($path)){
			$pem = file_get_contents($path);
			if(is_string($pem) && $pem !== ""){
				try{
					return ServerIdentity::fromPrivateKeyPem($pem);
				}catch(Throwable $e){
					$this->server->getLogger()->error("Could not load the NetherNet identity from $path: " . $e->getMessage());
				}
			}
		}

		$identity = ServerIdentity::generate();
		if(@file_put_contents($path, $identity->exportPrivateKeyPem()) === false){
			$this->server->getLogger()->warning("Could not save the NetherNet identity to $path; it will change on every restart");
		}

		return $identity;
	}

	public function start(){
		$this->nethernet->start();

		$this->server->getTickSleeper()->addNotifier($this->sleeper, function() : void{
			$this->process();
		});
		$this->ticker = new NetherNetTickerThread($this->sleeper, self::TICK_INTERVAL_MS);
		$this->ticker->start(PTHREADS_INHERIT_CONSTANTS); //HACK: MainLogger needs constants for exception logging
	}

	public function setNetwork(Network $network){
		$this->network = $network;
	}

	/**
	 * NetherNet sessions are already encrypted and authenticated by the transport: WebRTC runs over DTLS, and the
	 * server's fingerprint is signed with the identity from nethernet-identity.pem, which the client verifies.
	 * Clients on this transport do not answer the Minecraft-layer handshake, so asking for it would stall the login.
	 */
	public function isTransportEncrypted() : bool{
		return true;
	}

	public function process() : void{
		$this->nethernet->tick();
	}

	public function close(Player $player, string $reason = "unknown reason"){
		if(isset($this->identifiers[$h = spl_object_hash($player)])){
			$sessionId = $this->identifiers[$h];
			unset($this->players[$sessionId], $this->identifiersACK[$sessionId], $this->identifiers[$h]);
			$this->nethernet->closeSession($sessionId, DisconnectReason::SERVER_DISCONNECT);
		}
	}

	public function shutdown(){
		if($this->ticker !== null){
			$this->ticker->shutdown();
			$this->ticker = null;
		}
		$this->server->getTickSleeper()->removeNotifier($this->sleeper);

		//let the disconnects already queued for clients flush out before tearing the transport down
		$this->nethernet->initiateShutdown();
		$start = microtime(true);
		while($this->nethernet->getState() !== ServerState::STOPPED && (microtime(true) - $start) < self::SHUTDOWN_FLUSH_TIMEOUT){
			usleep(10000);
			$this->nethernet->tick();
		}
		$this->nethernet->shutdown();
	}

	public function emergencyShutdown(){
		if($this->ticker !== null){
			$this->ticker->shutdown();
			$this->ticker = null;
		}
		$this->server->getTickSleeper()->removeNotifier($this->sleeper);
		$this->nethernet->shutdown();
	}

	public function blockAddress(string $address, int $timeout = 300){
		$this->nethernet->blockAddress($address, $timeout);
	}

	public function unblockAddress(string $address){
		$this->nethernet->unblockAddress($address);
	}

	public function sendRawPacket(string $address, int $port, string $payload){
		//NetherNet has no connectionless datagrams; query responses cannot be sent over it
	}

	public function setName(string $name){
		$info = $this->server->getQueryInformation();
		$this->infoProvider->setServerInfo(new ServerInfo(
			serverName: $info->getServerName(),
			protocol: ProtocolInfo::CURRENT_PROTOCOL,
			gameVersion: ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			levelName: $info->getWorld(),
			gameType: GameType::tryFrom($this->server->getGamemode()) ?? GameType::SURVIVAL,
			playerCount: $info->getPlayerCount(),
			maxPlayerCount: $info->getMaxPlayerCount(),
			acceptsOnlineAuth: true,
			acceptsSelfSignedAuth: !$this->server->getOnlineMode()
		));
	}

	/**
	 * @return int|null
	 */
	public function putPacket(Player $player, DataPacket $packet, bool $needACK = false, bool $immediate = true){
		if(isset($this->identifiers[$h = spl_object_hash($player)])){
			$sessionId = $this->identifiers[$h];
			if(!$packet->isEncoded){
				$packet->encode();
			}

			if($packet instanceof BatchPacket){
				$cipher = $player->getCipher();
				//NetherNet messages carry the batch payload without the 0xFE game-packet ID
				$buffer = substr($packet->buffer, 1);
				$buffer = $cipher !== null ? $cipher->encrypt($buffer) : $buffer;

				$receiptId = $needACK ? $this->identifiersACK[$sessionId]++ : null;
				$this->nethernet->sendPacket($sessionId, $buffer, Reliability::RELIABLE, $receiptId);
				if(!$player->isFirstBatchConfigSequenceCompleted()){
					$player->setFirstBatchConfigSequenceCompleted(true);
				}
				return $receiptId;
			}else{
				$this->server->batchPackets([$player], [$packet], true, $immediate, $player->isFirstBatchConfigSequenceCompleted());
				return null;
			}
		}

		return null;
	}

	public function onSessionOpen(int $sessionId, string $networkId, InternetAddress $address, ?string $clientAddress, ?PeerIdentity $identity) : void{
		$ev = new PlayerCreationEvent($this, Player::class, Player::class, $clientAddress ?? $address->ip, $address->port);
		$ev->call();
		$class = $ev->getPlayerClass();

		/**
		 * @var Player $player
		 * @see Player::__construct()
		 */
		$player = new $class($this, $ev->getAddress(), $ev->getPort());

		/*
		 * The session only exists because the client proved this key to the transport (its identity assertion is signed
		 * by it and covers the connection's DTLS fingerprint), so it identifies the connection. The login is checked
		 * against it, which is what makes skipping the Minecraft-layer handshake safe.
		 */
		$player->setTransportIdentityKey(new NetherNetIdentityKey($identity?->publicKey));

		$this->players[$sessionId] = $player;
		$this->identifiersACK[$sessionId] = 0;
		$this->identifiers[spl_object_hash($player)] = $sessionId;
		$this->server->addPlayer($player);
	}

	public function onSessionClose(int $sessionId, DisconnectReason $reason) : void{
		if(isset($this->players[$sessionId])){
			$player = $this->players[$sessionId];
			unset($this->players[$sessionId], $this->identifiersACK[$sessionId], $this->identifiers[spl_object_hash($player)]);
			$player->close($player->getLeaveMessage(), $reason->getMessage());
		}
	}

	public function canAcceptPackets() : bool{
		return true;
	}

	public function onPacketReceive(int $sessionId, string $payload, Reliability $reliability) : void{
		if(isset($this->players[$sessionId])){
			//get this now for blocking in case the player was closed before the exception was raised
			$player = $this->players[$sessionId];

			try{
				if($payload !== ""){
					$cipher = $player->getCipher();
					$buffer = $cipher !== null ? $cipher->decrypt($payload) : $payload;

					$pk = new BatchPacket(self::MCPE_RAKNET_PACKET_ID . $buffer);
					$player->handleDataPacket($pk);
				}
			}catch(Throwable $e){
				$logger = $this->server->getLogger();
				$logger->debug("Packet " . (isset($pk) ? get_class($pk) : "unknown") . ": " . base64_encode($payload));
				$logger->logException($e);

				if((bool) $this->server->internalErrorKick === false){
					$player->sendMessage("Internal server error");
				}else{
					$address = $player->getAddress();
					$player->close($player->getLeaveMessage(), "Internal server error");
					$this->nethernet->blockAddress($address, 5);
				}
			}
		}
	}

	public function onPacketAck(int $sessionId, int $receiptId) : void{

	}

	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff) : void{
		$this->network?->addStatistics($bytesSentDiff, $bytesReceivedDiff);
	}

	public function onPingMeasure(int $sessionId, int $pingMs) : void{
		if(isset($this->players[$sessionId])){
			$this->players[$sessionId]->updatePing($pingMs);
		}
	}
}
