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

use pocketmine\nethernet\identity\AssertionIdentityVerifier;
use pocketmine\nethernet\identity\IdentityException;
use pocketmine\nethernet\identity\IdentityProvider;
use pocketmine\nethernet\protocol\ErrorCode;
use pocketmine\nethernet\sdp\SdpException;
use pocketmine\nethernet\sdp\SessionDescription;
use pocketmine\nethernet\transport\ConnectionBudgetConfiguration;
use pocketmine\nethernet\transport\PeerTransportFactory;
use pocketmine\nethernet\transport\TransportException;
use pocketmine\nethernet\utils\InternetAddress;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_values;
use function count;

final class TransportNegotiator implements Negotiator{

	public const DEFAULT_MAX_REMOTE_CANDIDATES = 32;
	public const DEFAULT_MAX_NEGOTIATIONS = 256;
	public const DEFAULT_MAX_NEGOTIATIONS_PER_ADDRESS = 16;

	/**
	 * @var TransportNegotiation[]
	 * @phpstan-var array<int, TransportNegotiation>
	 */
	private array $negotiations = [];

	/**
	 * @var EstablishedPeer[]
	 * @phpstan-var list<EstablishedPeer>
	 */
	private array $established = [];

	/** @phpstan-var array<string, true> */
	private array $floodingAddresses = [];

	private int $nextNegotiationId = 0;
	private bool $shutDown = false;

	private readonly AnswerBuilder $answerBuilder;

	/**
	 * @param string[]|null $advertisedAddresses
	 * @phpstan-param list<string>|null $advertisedAddresses
	 */
	public function __construct(
		IdentityProvider $identityProvider,
		private readonly AssertionIdentityVerifier $identityVerifier,
		private readonly PeerTransportFactory $transportFactory,
		private readonly ConnectionBudgetConfiguration $budget,
		private readonly float $gatheringTimeout,
		private readonly float $channelTimeout,
		private readonly int $maxRemoteCandidates,
		private readonly ?int $maxNegotiations,
		private readonly ?int $maxNegotiationsPerAddress,
		private readonly \Logger $logger,
		?array $advertisedAddresses = null
	){
		$this->answerBuilder = new AnswerBuilder($identityProvider, $advertisedAddresses);
	}

	public function beginNegotiation(string $offerSdp, string $networkId, CandidateMode $candidateMode = CandidateMode::BUNDLED, ?string $peerAddress = null) : Negotiation{
		if($this->shutDown){
			throw new NegotiationException("Negotiator is shut down", ErrorCode::NO_SIGNALING_CHANNEL);
		}
		if($peerAddress !== null){
			$peerAddress = InternetAddress::normalizeIp($peerAddress) ?? throw new \InvalidArgumentException("Peer address \"$peerAddress\" is not an IP address");
		}
		$this->checkLimits($peerAddress);

		try{
			$offer = SessionDescription::parse($offerSdp);
			$offer->validate();
			$fingerprint = $offer->getFingerprint();
		}catch(SdpException $e){
			throw new NegotiationException("Offer is invalid: " . $e->getMessage(), ErrorCode::FAILED_TO_SET_REMOTE_DESCRIPTION, $e);
		}

		$candidates = array_merge($offer->getSessionAttributeValues("candidate"), $offer->getMediaAttributeValues("candidate"));
		$usable = array_values(array_filter($candidates, IceCandidate::isAcceptableFromPeer(...)));
		if(count($usable) !== count($candidates)){
			$this->logger->debug("Offer from $networkId carries " . (count($candidates) - count($usable)) . " ICE candidates that are malformed or do not name an IP address, skipping them");
			$offer = $offer->withCandidates($usable);
		}

		$candidateCount = $offer->countCandidates();
		if($candidateCount > $this->maxRemoteCandidates){
			$this->logger->debug("Offer from $networkId carries $candidateCount ICE candidates, using the first " . $this->maxRemoteCandidates);
			$offer = $offer->withCandidateLimit($this->maxRemoteCandidates);
		}

		try{
			$identity = $this->identityVerifier->verify($offer->getIdentity(), $fingerprint);
		}catch(IdentityException $e){
			throw new NegotiationException("Identity rejected: " . $e->getMessage(), ErrorCode::IDENTITY_NOT_ALLOWED, $e);
		}

		try{
			$transport = $this->transportFactory->create($this->budget);
		}catch(TransportException $e){
			throw new NegotiationException("Could not create a peer connection: " . $e->getMessage(), ErrorCode::FAILED_TO_CREATE_PEER_CONNECTION, $e);
		}

		try{
			$transport->setRemoteOffer($offer->withoutIdentity()->toString());
		}catch(TransportException $e){
			$transport->close();

			throw new NegotiationException("Peer connection rejected the offer: " . $e->getMessage(), ErrorCode::FAILED_TO_SET_REMOTE_DESCRIPTION, $e);
		}

		$negotiation = new TransportNegotiation(
			$transport,
			$networkId,
			$peerAddress,
			$identity,
			$candidateMode,
			$this->answerBuilder,
			$this->gatheringTimeout,
			$this->channelTimeout,
			$this->maxRemoteCandidates
		);
		$this->negotiations[$this->nextNegotiationId++] = $negotiation;

		if($peerAddress !== null){
			$this->addSignalingPeerCandidate($negotiation, $offer, $peerAddress);
		}

		return $negotiation;
	}

	/**
	 * Refuses the offer before anything is spent on it: each negotiation holds a peer connection until it ends.
	 *
	 * @throws NegotiationException
	 */
	private function checkLimits(?string $peerAddress) : void{
		$total = 0;
		$fromAddress = 0;
		foreach($this->negotiations as $negotiation){
			if($negotiation->isFinished()){
				continue;
			}
			$total++;
			if($peerAddress !== null && $negotiation->getClientAddress() === $peerAddress){
				$fromAddress++;
			}
		}

		if($peerAddress !== null && $this->maxNegotiationsPerAddress !== null && $fromAddress >= $this->maxNegotiationsPerAddress){
			$this->floodingAddresses[$peerAddress] = true;

			throw new NegotiationException("$peerAddress already has $fromAddress negotiations in progress", ErrorCode::INCOMING_CONNECTION_IGNORED);
		}
		if($this->maxNegotiations !== null && $total >= $this->maxNegotiations){
			throw new NegotiationException("Negotiation limit of $this->maxNegotiations reached", ErrorCode::INCOMING_CONNECTION_IGNORED);
		}
	}

	private function addSignalingPeerCandidate(TransportNegotiation $negotiation, SessionDescription $offer, string $peerAddress) : void{
		$candidate = IceCandidateFormatter::signalingPeer($offer, $peerAddress);
		if($candidate === null){
			return;
		}

		try{
			$negotiation->addRemoteCandidate($candidate);
		}catch(NegotiationException $e){
			$this->logger->debug("Signaling peer candidate for " . $negotiation->getNetworkId() . " was rejected: " . $e->getMessage());
		}
	}

	public function tick(float $now) : void{
		if($this->shutDown){
			return;
		}

		foreach($this->negotiations as $id => $negotiation){
			$negotiation->update($now);
			if(!$negotiation->isFinished()){
				continue;
			}
			unset($this->negotiations[$id]);

			$peer = $negotiation->takeEstablishedPeer();
			if($peer !== null){
				$this->established[] = $peer;
			}else{
				$this->logger->debug("Negotiation for " . $negotiation->getNetworkId() . " failed: " . ($negotiation->getFailureReason() ?? "no reason given"));
			}
		}
	}

	public function takeEstablished() : array{
		$taken = $this->established;
		$this->established = [];

		return $taken;
	}

	public function takeFloodingAddresses() : array{
		$taken = array_keys($this->floodingAddresses);
		$this->floodingAddresses = [];

		return $taken;
	}

	public function shutdown() : void{
		if($this->shutDown){
			return;
		}
		$this->shutDown = true;

		foreach($this->negotiations as $negotiation){
			$negotiation->fail("Negotiator is shutting down", ErrorCode::NO_SIGNALING_CHANNEL);
		}
		$this->negotiations = [];

		foreach($this->established as $peer){
			$peer->transport->close();
		}
		$this->established = [];
		$this->floodingAddresses = [];
	}
}
