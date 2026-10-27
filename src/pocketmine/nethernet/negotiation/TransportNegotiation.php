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

namespace pocketmine\nethernet\negotiation;

use pocketmine\nethernet\identity\IdentityException;
use pocketmine\nethernet\identity\PeerIdentity;
use pocketmine\nethernet\protocol\ErrorCode;
use pocketmine\nethernet\protocol\Reliability;
use pocketmine\nethernet\sdp\SdpException;
use pocketmine\nethernet\transport\ChannelPair;
use pocketmine\nethernet\transport\MessageChannel;
use pocketmine\nethernet\transport\PeerTransport;
use pocketmine\nethernet\transport\TransportException;

/**
 * @internal
 */
final class TransportNegotiation implements Negotiation{

	private NegotiationState $state = NegotiationState::GATHERING;
	private ?float $deadline = null;

	private ?LocalAnswer $answer = null;
	private ?string $failureReason = null;
	private ErrorCode $failureCode = ErrorCode::NONE;

	private int $candidateIndex = 0;
	private int $remoteCandidateCount = 0;

	/** @phpstan-var array<string, MessageChannel> keyed by Reliability case name */
	private array $channels = [];

	private ?EstablishedPeer $establishedPeer = null;

	public function __construct(
		private readonly PeerTransport $transport,
		private readonly string $networkId,
		private readonly ?string $clientAddress,
		private readonly ?PeerIdentity $identity,
		private readonly CandidateMode $candidateMode,
		private readonly AnswerBuilder $answerBuilder,
		private readonly float $gatheringTimeout,
		private readonly float $channelTimeout,
		private readonly int $maxRemoteCandidates
	){}

	public function getNetworkId() : string{ return $this->networkId; }

	public function getClientAddress() : ?string{ return $this->clientAddress; }

	public function getAnswer() : ?string{ return $this->answer?->sdp; }

	public function isFinished() : bool{ return $this->state->isFinished(); }

	public function isFailed() : bool{ return $this->state === NegotiationState::FAILED; }

	public function isClosed() : bool{
		return $this->state === NegotiationState::FAILED || $this->transport->getState()->isBroken();
	}

	public function getFailureReason() : ?string{ return $this->failureReason; }

	public function getFailureCode() : ErrorCode{ return $this->failureCode; }

	public function addRemoteCandidate(string $candidate) : void{
		if($this->state->isFinished()){
			return;
		}
		if($this->remoteCandidateCount >= $this->maxRemoteCandidates){
			return;
		}
		$this->remoteCandidateCount++;

		if(!IceCandidate::isAcceptableFromPeer($candidate)){
			throw new NegotiationException("Peer sent a candidate that is malformed or does not name an IP address", ErrorCode::CANDIDATE_ADD);
		}

		try{
			$this->transport->addRemoteCandidate($candidate);
		}catch(TransportException $e){
			throw new NegotiationException("Peer sent an unusable candidate: " . $e->getMessage(), ErrorCode::CANDIDATE_ADD, $e);
		}
	}

	public function takeLocalCandidates() : array{
		$answer = $this->answer;
		if($answer === null || $this->state->isFinished() || $this->candidateMode === CandidateMode::BUNDLED){
			return [];
		}

		try{
			$gathered = $this->transport->pollLocalCandidates();
		}catch(TransportException){
			//the next update notices the broken transport and fails the negotiation
			return [];
		}

		$formatted = [];
		foreach($gathered as $candidate){
			try{
				$formatted[] = IceCandidateFormatter::format($candidate, $answer->iceUfrag, $this->candidateIndex++);
			}catch(NegotiationException){
				//a candidate the transport produced in a form that cannot be signaled is simply not offered
				continue;
			}
		}

		return $formatted;
	}

	public function fail(string $reason, ErrorCode $code = ErrorCode::GENERIC_FAILURE) : void{
		if($this->state->isFinished()){
			return;
		}

		$this->state = NegotiationState::FAILED;
		$this->failureReason = $reason;
		$this->failureCode = $code;

		foreach($this->channels as $channel){
			$channel->close();
		}
		$this->transport->close();
	}

	public function close() : void{
		if(!$this->state->isFinished()){
			$this->fail("Closed");

			return;
		}

		$this->transport->close();
	}

	public function update(float $now) : void{
		if($this->state->isFinished()){
			return;
		}

		$transportState = $this->transport->getState();
		if($transportState->isBroken()){
			$this->fail("Peer connection entered state " . $transportState->name, ErrorCode::ICE);

			return;
		}

		try{
			match($this->state){
				NegotiationState::GATHERING => $this->updateGathering($now),
				NegotiationState::ANSWERED => $this->updateAnswered($now),
				NegotiationState::ESTABLISHED, NegotiationState::FAILED => null
			};
		}catch(TransportException $e){
			$this->fail("Peer connection failure: " . $e->getMessage(), match($this->state){
				NegotiationState::GATHERING => ErrorCode::FAILED_TO_CREATE_ANSWER,
				default => ErrorCode::ICE
			});
		}
	}

	public function takeEstablishedPeer() : ?EstablishedPeer{
		$peer = $this->establishedPeer;
		$this->establishedPeer = null;

		return $peer;
	}

	/**
	 * @throws TransportException
	 */
	private function updateGathering(float $now) : void{
		/* A bundled answer carries every candidate, so it cannot be built until gathering is over. */
		if($this->candidateMode === CandidateMode::BUNDLED && !$this->transport->isGatheringComplete()){
			$this->failIfTimedOut($now, $this->gatheringTimeout, "Timed out gathering ICE candidates", ErrorCode::ICE);

			return;
		}

		$localDescription = $this->transport->getLocalDescription();
		if($localDescription === null){
			$this->failIfTimedOut($now, $this->gatheringTimeout, "Timed out waiting for a local description", ErrorCode::FAILED_TO_CREATE_ANSWER);

			return;
		}

		try{
			$this->answer = $this->answerBuilder->build($localDescription, $this->candidateMode);
		}catch(SdpException $e){
			$this->fail("Could not build an answer: " . $e->getMessage(), ErrorCode::FAILED_TO_CREATE_ANSWER);

			return;
		}catch(IdentityException $e){
			$this->fail("Could not issue an identity assertion: " . $e->getMessage(), ErrorCode::FAILED_TO_CREATE_IDENTITY_ASSERTION);

			return;
		}catch(NegotiationException $e){
			$this->fail($e->getMessage(), $e->getErrorCode());

			return;
		}

		$this->enter(NegotiationState::ANSWERED);
	}

	/**
	 * @throws TransportException
	 */
	private function updateAnswered(float $now) : void{
		foreach($this->transport->pollChannels() as $channel){
			$reliability = $channel->getReliability();
			if($reliability === null){
				$channel->close();
				$this->fail("Unsupported data channel: " . $channel->getLabel(), ErrorCode::DATA_CHANNEL_CLOSED);

				return;
			}
			if(isset($this->channels[$reliability->name])){
				$channel->close();
				$this->fail("Duplicate data channel opened: " . $reliability->getChannelLabel(), ErrorCode::DATA_CHANNEL_CLOSED);

				return;
			}

			$this->channels[$reliability->name] = $channel;
		}

		$reliable = $this->channels[Reliability::RELIABLE->name] ?? null;
		$unreliable = $this->channels[Reliability::UNRELIABLE->name] ?? null;
		if($reliable === null || $unreliable === null || !$reliable->isOpen() || !$unreliable->isOpen()){
			$this->failIfTimedOut($now, $this->channelTimeout, "Peer did not open both data channels", ErrorCode::NEGOTIATION_TIMEOUT_WAITING_FOR_ACCEPT);

			return;
		}

		$address = $this->transport->getRemoteAddress();
		if($address === null){
			$this->fail("Data channels opened without a selected candidate pair", ErrorCode::ICE);

			return;
		}

		$this->establishedPeer = new EstablishedPeer($this->transport, new ChannelPair($reliable, $unreliable), $this->networkId, $address, $this->clientAddress, $this->identity);
		$this->enter(NegotiationState::ESTABLISHED);
	}

	private function enter(NegotiationState $state) : void{
		$this->state = $state;
		$this->deadline = null;
	}

	private function failIfTimedOut(float $now, float $timeout, string $reason, ErrorCode $code) : void{
		$this->deadline ??= $now + $timeout;
		if($now >= $this->deadline){
			$this->fail($reason, $code);
		}
	}
}
