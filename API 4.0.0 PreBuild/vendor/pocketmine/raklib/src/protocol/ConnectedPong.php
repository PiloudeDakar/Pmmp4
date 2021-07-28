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

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class ConnectedPong extends ConnectedPacket{
	public static $ID = MessageIdentifiers::ID_CONNECTED_PONG;

	/** @var int */
	public $sendPingTime;
	/** @var int */
	public $sendPongTime;

	public static function create(int $sendPingTime, int $sendPongTime) : self{
		$result = new self;
		$result->sendPingTime = $sendPingTime;
		$result->sendPongTime = $sendPongTime;
		return $result;
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putLong($this->sendPingTime);
		$out->putLong($this->sendPongTime);
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->sendPingTime = $in->getLong();
		$this->sendPongTime = $in->getLong();
	}
}
