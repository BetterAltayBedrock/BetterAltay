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

use function strlen;

/**
 * @internal
 */
final class HttpResponse{

	public const HEADER_CONTENT_TYPE = "Content-Type";
	public const HEADER_CONTENT_LENGTH = "Content-Length";
	public const HEADER_CONNECTION = "Connection";
	public const HEADER_LOCATION = "Location";

	/**
	 * @param string[] $headers
	 * @phpstan-param array<string, string> $headers
	 */
	public function __construct(
		public readonly HttpStatus $status,
		public readonly string $body = "",
		public readonly ?string $contentType = null,
		public readonly array $headers = []
	){}

	/**
	 * @param bool $omitBody Whether to leave the body out while still declaring its length, as a HEAD response does.
	 */
	public function encode(bool $omitBody = false) : string{
		$head = "HTTP/1.1 " . $this->status->value . " " . $this->status->getReasonPhrase() . "\r\n";
		if($this->contentType !== null){
			$head .= self::HEADER_CONTENT_TYPE . ": $this->contentType\r\n";
		}
		foreach($this->headers as $name => $value){
			$head .= "$name: $value\r\n";
		}
		$head .= self::HEADER_CONTENT_LENGTH . ": " . strlen($this->body) . "\r\n";
		$head .= self::HEADER_CONNECTION . ": close\r\n\r\n";

		return $head . ($omitBody ? "" : $this->body);
	}
}
