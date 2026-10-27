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

namespace pocketmine\nethernet\signaling\http;

use pocketmine\nethernet\signaling\SignalingException;
use function array_filter;
use function is_file;
use function is_readable;

final class TlsConfiguration{

	/**
	 * @param string      $certificateFile PEM file holding the certificate chain, and the private key too unless one is given separately.
	 * @param string|null $privateKeyFile  PEM file holding the private key.
	 * @param string|null $passphrase      Passphrase of the private key, if it is encrypted.
	 */
	public function __construct(
		public readonly string $certificateFile,
		public readonly ?string $privateKeyFile = null,
		public readonly ?string $passphrase = null
	){}

	/**
	 * @throws SignalingException if a configured file cannot be read
	 */
	public function checkReadable() : void{
		foreach([$this->certificateFile, $this->privateKeyFile] as $path){
			if($path !== null && (!is_file($path) || !is_readable($path))){
				throw new SignalingException("TLS is configured with $path, which cannot be read");
			}
		}
	}

	/**
	 * Returns the options for PHP's `ssl` stream context.
	 *
	 * @return string[]
	 * @phpstan-return array<string, string>
	 */
	public function getContextOptions() : array{
		return array_filter([
			"local_cert" => $this->certificateFile,
			"local_pk" => $this->privateKeyFile,
			"passphrase" => $this->passphrase
		], static fn(?string $value) : bool => $value !== null);
	}
}
