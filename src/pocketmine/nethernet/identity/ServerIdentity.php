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

namespace pocketmine\nethernet\identity;

use pocketmine\nethernet\crypto\CryptoException;
use pocketmine\nethernet\crypto\Der;
use pocketmine\nethernet\crypto\Es384;
use pocketmine\nethernet\crypto\OpenSsl;
use pocketmine\nethernet\crypto\P384;
use function base64_encode;
use function chunk_split;
use function is_array;
use function is_string;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use function openssl_pkey_new;

/**
 * The P-384 key pair a host identifies itself with.
 */
final class ServerIdentity{

	private function __construct(
		private readonly \OpenSSLAsymmetricKey $privateKey,
		private readonly PublicKey $publicKey
	){}

	/**
	 * @throws IdentityException
	 */
	public static function generate() : self{
		$key = openssl_pkey_new(["ec" => ["curve_name" => P384::OPENSSL_CURVE_NAME]]);
		if($key === false){
			throw new IdentityException("OpenSSL could not generate a key: " . OpenSsl::lastError());
		}

		return new self($key, PublicKey::fromOpenSslKey($key));
	}

	/**
	 * @throws IdentityException
	 */
	public static function fromPrivateKeyPem(string $pem, ?string $passphrase = null) : self{
		$key = openssl_pkey_get_private($pem, $passphrase);
		if($key === false){
			throw new IdentityException("OpenSSL could not read the private key: " . OpenSsl::lastError());
		}

		return new self($key, PublicKey::fromOpenSslKey($key));
	}

	/**
	 * Returns the ES384 signature of the input, in JOSE form.
	 *
	 * @throws IdentityException
	 */
	public function sign(string $input) : string{
		try{
			return Es384::sign($input, $this->privateKey);
		}catch(CryptoException $e){
			throw new IdentityException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * Returns the key pair as an unencrypted PKCS#8 PEM. Written by hand because OpenSSL's own export
	 * needs a configuration file that is often missing on Windows.
	 *
	 * @throws IdentityException
	 */
	public function exportPrivateKeyPem() : string{
		[$d, $x, $y] = $this->readComponents();

		$ecPrivateKey = Der::sequence(
			Der::integer("\x01") .
			Der::octetString($d) .
			Der::tagged(1, Der::bitString("\x04" . $x . $y))
		);
		$der = Der::sequence(
			Der::integer("\x00") .
			Der::sequence(P384::ALGORITHM_IDENTIFIER) .
			Der::octetString($ecPrivateKey)
		);

		return "-----BEGIN PRIVATE KEY-----\n"
			. chunk_split(base64_encode($der), 64, "\n")
			. "-----END PRIVATE KEY-----\n";
	}

	/**
	 * @phpstan-return array{string, string, string}
	 *
	 * @throws IdentityException
	 */
	private function readComponents() : array{
		$details = openssl_pkey_get_details($this->privateKey);
		if($details === false || !isset($details["ec"]) || !is_array($details["ec"])){
			throw new IdentityException("Failed to extract key components from OpenSSL key: " . OpenSsl::lastError());
		}

		$components = [];
		foreach(["d", "x", "y"] as $name){
			$value = $details["ec"][$name] ?? null;
			if(!is_string($value) || $value === ""){
				throw new IdentityException("The key is missing its $name component");
			}
			try{
				$components[] = P384::padCoordinate($value);
			}catch(CryptoException $e){
				throw new IdentityException($e->getMessage(), 0, $e);
			}
		}

		return [$components[0], $components[1], $components[2]];
	}

	public function getPublicKey() : PublicKey{ return $this->publicKey; }

	/** @internal */
	public function getPrivateKey() : \OpenSSLAsymmetricKey{ return $this->privateKey; }
}
