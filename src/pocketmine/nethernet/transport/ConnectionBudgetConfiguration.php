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

namespace pocketmine\nethernet\transport;

use pocketmine\nethernet\protocol\framing\Segmenter;

/**
 * Resource limits and queue thresholds for a single peer connection.
 */
final class ConnectionBudgetConfiguration{

	/** Advertised in SDP answers as `a=max-message-size`. */
	public const DEFAULT_MAX_MESSAGE_SIZE = Segmenter::MAX_SEGMENT_PAYLOAD_SIZE + Segmenter::HEADER_SIZE;

	/** The largest message a vanilla client sends: 256 segments of 9999 payload bytes. */
	public const DEFAULT_MAX_PAYLOAD_SIZE = 256 * 9999;

	/** Room for every segment of one such message to be waiting at once, counter bytes included. */
	public const DEFAULT_MAX_RECEIVE_QUEUE_SIZE = 256 * 10000;

	public const DEFAULT_MAX_RECEIVE_QUEUE_MESSAGES = 512;

	public const DEFAULT_MAX_SEND_QUEUE_SIZE = 4194304;

	/**
	 * A peer requires 2 data channels; extra headroom is provided because the native extension tracks table
	 * entries rather than active channels.
	 */
	public const DEFAULT_MAX_PENDING_DATA_CHANNELS = 4;

	/**
	 * @param int $maxMessageSize          Maximum SCTP message size in bytes.
	 * @param int $maxPayloadSize          Maximum reassembled message payload size in bytes.
	 * @param int $maxReceiveQueueSize     Maximum unread incoming bytes before dropping a session.
	 * @param int $maxReceiveQueueMessages Maximum unread incoming messages before dropping a session.
	 * @param int $maxSendQueueSize        Maximum queued outgoing bytes before dropping a session.
	 * @param int $maxPendingDataChannels  Maximum pending data channels permitted per connection.
	 */
	public function __construct(
		public readonly int $maxMessageSize = self::DEFAULT_MAX_MESSAGE_SIZE,
		public readonly int $maxPayloadSize = self::DEFAULT_MAX_PAYLOAD_SIZE,
		public readonly int $maxReceiveQueueSize = self::DEFAULT_MAX_RECEIVE_QUEUE_SIZE,
		public readonly int $maxReceiveQueueMessages = self::DEFAULT_MAX_RECEIVE_QUEUE_MESSAGES,
		public readonly int $maxSendQueueSize = self::DEFAULT_MAX_SEND_QUEUE_SIZE,
		public readonly int $maxPendingDataChannels = self::DEFAULT_MAX_PENDING_DATA_CHANNELS
	){
		if($maxMessageSize < 2 || $maxMessageSize > self::DEFAULT_MAX_MESSAGE_SIZE){
			throw new \InvalidArgumentException("Maximum message size must be between 2 and " . self::DEFAULT_MAX_MESSAGE_SIZE . ", got $maxMessageSize");
		}
		if($maxPayloadSize < $maxMessageSize - Segmenter::HEADER_SIZE || $maxPayloadSize > Segmenter::MAX_PAYLOAD_SIZE){
			throw new \InvalidArgumentException("Maximum payload size must be between " . ($maxMessageSize - Segmenter::HEADER_SIZE) . " and " . Segmenter::MAX_PAYLOAD_SIZE . ", got $maxPayloadSize");
		}
		if($maxReceiveQueueSize < $maxMessageSize){
			throw new \InvalidArgumentException("Maximum receive queue size must be at least the size of one message ($maxMessageSize bytes), got $maxReceiveQueueSize");
		}
		if($maxReceiveQueueMessages < 1){
			throw new \InvalidArgumentException("Maximum receive queue message count must be positive, got $maxReceiveQueueMessages");
		}
		if($maxSendQueueSize < $maxMessageSize){
			throw new \InvalidArgumentException("Maximum send queue size must be at least the size of one message ($maxMessageSize bytes), got $maxSendQueueSize");
		}
		if($maxPendingDataChannels < 2){
			throw new \InvalidArgumentException("Maximum pending data channels must be at least 2, got $maxPendingDataChannels");
		}
	}
}
