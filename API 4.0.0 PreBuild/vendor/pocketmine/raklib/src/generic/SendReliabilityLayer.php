<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace raklib\generic;

use raklib\protocol\ACK;
use raklib\protocol\Datagram;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\NACK;
use raklib\protocol\PacketReliability;
use raklib\protocol\SplitPacketInfo;
use function array_fill;
use function assert;
use function count;
use function str_split;
use function strlen;
use function time;

final class SendReliabilityLayer{

	/**
	 * @var \Closure
	 * @phpstan-var \Closure(Datagram) : void
	 */
	private $sendDatagramCallback;
	/**
	 * @var \Closure
	 * @phpstan-var \Closure(int) : void
	 */
	private $onACK;

	/** @var int */
	private $mtuSize;

	/** @var EncapsulatedPacket[] */
	private $sendQueue = [];

	/** @var int */
	private $splitID = 0;

	/** @var int */
	private $sendSeqNumber = 0;

	/** @var int */
	private $messageIndex = 0;

	/** @var int[] */
	private $sendOrderedIndex;
	/** @var int[] */
	private $sendSequencedIndex;

	/** @var Datagram[] */
	private $resendQueue = [];

	/** @var ReliableCacheEntry[] */
	private $reliableCache = [];

	/** @var int[][] */
	private $needACK = [];

	/**
	 * @phpstan-param \Closure(Datagram) : void $sendDatagram
	 * @phpstan-param \Closure(int) : void      $onACK
	 */
	public function __construct(int $mtuSize, \Closure $sendDatagram, \Closure $onACK){
		$this->mtuSize = $mtuSize;
		$this->sendDatagramCallback = $sendDatagram;
		$this->onACK = $onACK;

		$this->sendOrderedIndex = array_fill(0, PacketReliability::MAX_ORDER_CHANNELS, 0);
		$this->sendSequencedIndex = array_fill(0, PacketReliability::MAX_ORDER_CHANNELS, 0);
	}

	private function sendDatagram(Datagram $datagram) : void{
		if($datagram->seqNumber !== null){
			unset($this->reliableCache[$datagram->seqNumber]);
		}
		$datagram->seqNumber = $this->sendSeqNumber++;
		($this->sendDatagramCallback)($datagram);

		$resendable = [];
		foreach($datagram->packets as $pk){
			if(PacketReliability::isReliable($pk->reliability)){
				$resendable[] = $pk;
			}
		}
		if(count($resendable) !== 0){
			$this->reliableCache[$datagram->seqNumber] = new ReliableCacheEntry($resendable);
		}
	}

	public function sendQueue() : void{
		if(count($this->sendQueue) > 0){
			$datagram = new Datagram();
			$datagram->packets = $this->sendQueue;
			$this->sendDatagram($datagram);
			$this->sendQueue = [];
		}
	}

	private function addToQueue(EncapsulatedPacket $pk, bool $immediate) : void{
		if($pk->identifierACK !== null and $pk->messageIndex !== null){
			$this->needACK[$pk->identifierACK][$pk->messageIndex] = $pk->messageIndex;
		}

		$length = Datagram::HEADER_SIZE;
		foreach($this->sendQueue as $queued){
			$length += $queued->getTotalLength();
		}

		if($length + $pk->getTotalLength() > $this->mtuSize - 36){ //IP header (20 bytes) + UDP header (8 bytes) + RakNet weird (8 bytes) = 36 bytes
			$this->sendQueue();
		}

		if($pk->identifierACK !== null){
			$this->sendQueue[] = clone $pk;
			$pk->identifierACK = null;
		}else{
			$this->sendQueue[] = $pk;
		}

		if($immediate){
			// Forces pending sends to go out now, rather than waiting to the next update interval
			$this->sendQueue();
		}
	}

	public function addEncapsulatedToQueue(EncapsulatedPacket $packet, bool $immediate = false) : void{
		if($packet->identifierACK !== null){
			$this->needACK[$packet->identifierACK] = [];
		}

		if(PacketReliability::isOrdered($packet->reliability)){
			$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel]++;
		}elseif(PacketReliability::isSequenced($packet->reliability)){
			$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel]; //sequenced packets don't increment the ordered channel index
			$packet->sequenceIndex = $this->sendSequencedIndex[$packet->orderChannel]++;
		}

		//IP header size (20 bytes) + UDP header size (8 bytes) + RakNet weird (8 bytes) + datagram header size (4 bytes) + max encapsulated packet header size (20 bytes)
		$maxSize = $this->mtuSize - 60;

		if(strlen($packet->buffer) > $maxSize){
			$buffers = str_split($packet->buffer, $maxSize);
			assert($buffers !== false);
			$bufferCount = count($buffers);

			$splitID = ++$this->splitID % 65536;
			foreach($buffers as $count => $buffer){
				$pk = new EncapsulatedPacket();
				$pk->splitInfo = new SplitPacketInfo($splitID, $count, $bufferCount);
				$pk->reliability = $packet->reliability;
				$pk->buffer = $buffer;

				if(PacketReliability::isReliable($pk->reliability)){
					$pk->messageIndex = $this->messageIndex++;
				}

				$pk->sequenceIndex = $packet->sequenceIndex;
				$pk->orderChannel = $packet->orderChannel;
				$pk->orderIndex = $packet->orderIndex;

				$this->addToQueue($pk, true);
			}
		}else{
			if(PacketReliability::isReliable($packet->reliability)){
				$packet->messageIndex = $this->messageIndex++;
			}
			$this->addToQueue($packet, false);
		}
	}

	public function onACK(ACK $packet) : void{
		foreach($packet->packets as $seq){
			if(isset($this->reliableCache[$seq])){
				foreach($this->reliableCache[$seq]->getPackets() as $pk){
					if($pk->identifierACK !== null and $pk->messageIndex !== null){
						unset($this->needACK[$pk->identifierACK][$pk->messageIndex]);
						if(count($this->needACK[$pk->identifierACK]) === 0){
							unset($this->needACK[$pk->identifierACK]);
							($this->onACK)($pk->identifierACK);
						}
					}
				}
				unset($this->reliableCache[$seq]);
			}
		}
	}

	public function onNACK(NACK $packet) : void{
		foreach($packet->packets as $seq){
			if(isset($this->reliableCache[$seq])){
				//TODO: group resends if the resulting datagram is below the MTU
				$resend = new Datagram();
				$resend->packets = $this->reliableCache[$seq]->getPackets();
				$this->resendQueue[] = $resend;
				unset($this->reliableCache[$seq]);
			}
		}
	}

	public function needsUpdate() : bool{
		return (
			count($this->sendQueue) !== 0 or
			count($this->resendQueue) !== 0 or
			count($this->reliableCache) !== 0
		);
	}

	public function update() : void{
		if(count($this->resendQueue) > 0){
			$limit = 16;
			foreach($this->resendQueue as $k => $pk){
				$this->sendDatagram($pk);
				unset($this->resendQueue[$k]);

				if(--$limit <= 0){
					break;
				}
			}

			if(count($this->resendQueue) > ReceiveReliabilityLayer::$WINDOW_SIZE){
				$this->resendQueue = [];
			}
		}

		foreach($this->reliableCache as $seq => $pk){
			if($pk->getTimestamp() < (time() - 8)){
				$resend = new Datagram();
				$resend->packets = $pk->getPackets();
				$this->resendQueue[] = $resend;
				unset($this->reliableCache[$seq]);
			}else{
				break;
			}
		}

		$this->sendQueue();
	}
}
