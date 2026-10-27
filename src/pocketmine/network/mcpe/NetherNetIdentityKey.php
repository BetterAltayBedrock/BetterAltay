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

use pocketmine\nethernet\identity\PublicKey;
use pocketmine\network\TransportIdentityException;
use pocketmine\network\TransportIdentityKey;
use function hash;
use function hash_equals;

/**
 * The identity a client proved over NetherNet. Its identity assertion is signed by that key and bound to the
 * connection's DTLS fingerprint, so the key identifies the connection rather than the login packet, and the login
 * chain has to name the same key.
 *
 * The key is null when the session carries no identity, which the transport only allows when it accepts anonymous
 * peers.
 */
final class NetherNetIdentityKey implements TransportIdentityKey{

	public function __construct(
		private readonly ?PublicKey $key
	){}

	public function getKey() : ?string{
		return $this->key?->getDigest();
	}

	public function verify(string $clientPublicKey) : void{
		if($this->key === null){
			throw new TransportIdentityException("Missing NetherNet identity for this connection");
		}
		if(!hash_equals($this->key->getDigest(), hash("sha256", $clientPublicKey, binary: true))){
			throw new TransportIdentityException("Login public key does not match the NetherNet identity of this connection");
		}
	}
}
