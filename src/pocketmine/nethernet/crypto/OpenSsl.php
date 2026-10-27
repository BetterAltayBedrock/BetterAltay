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

namespace pocketmine\nethernet\crypto;

use function openssl_error_string;

/**
 * @internal
 */
final class OpenSsl{

	private function __construct(){
		//NOOP
	}

	public static function lastError() : string{
		$message = null;
		while(($error = openssl_error_string()) !== false){
			$message = $error;
		}

		return $message ?? "no detail available";
	}
}
