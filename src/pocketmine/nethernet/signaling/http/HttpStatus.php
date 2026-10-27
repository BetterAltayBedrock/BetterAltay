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

enum HttpStatus : int{
	case OK = 200;
	case PERMANENT_REDIRECT = 308;
	case BAD_REQUEST = 400;
	case NOT_FOUND = 404;
	case METHOD_NOT_ALLOWED = 405;
	case REQUEST_TIMEOUT = 408;
	case LENGTH_REQUIRED = 411;
	case CONTENT_TOO_LARGE = 413;
	case URI_TOO_LONG = 414;
	case UNSUPPORTED_MEDIA_TYPE = 415;
	case REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
	case INTERNAL_SERVER_ERROR = 500;
	case NOT_IMPLEMENTED = 501;

	public function getReasonPhrase() : string{
		return match($this){
			self::OK => "OK",
			self::PERMANENT_REDIRECT => "Permanent Redirect",
			self::BAD_REQUEST => "Bad Request",
			self::NOT_FOUND => "Not Found",
			self::METHOD_NOT_ALLOWED => "Method Not Allowed",
			self::REQUEST_TIMEOUT => "Request Timeout",
			self::LENGTH_REQUIRED => "Length Required",
			self::CONTENT_TOO_LARGE => "Content Too Large",
			self::URI_TOO_LONG => "URI Too Long",
			self::UNSUPPORTED_MEDIA_TYPE => "Unsupported Media Type",
			self::REQUEST_HEADER_FIELDS_TOO_LARGE => "Request Header Fields Too Large",
			self::INTERNAL_SERVER_ERROR => "Internal Server Error",
			self::NOT_IMPLEMENTED => "Not Implemented"
		};
	}
}
