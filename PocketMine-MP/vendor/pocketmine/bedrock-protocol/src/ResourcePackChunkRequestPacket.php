<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class ResourcePackChunkRequestPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::RESOURCE_PACK_CHUNK_REQUEST_PACKET;

	public string $packId;
	public int $chunkIndex;

	/**
	 * @generate-create-func
	 */
	public static function create(string $packId, int $chunkIndex) : self{
		$result = new self;
		$result->packId = $packId;
		$result->chunkIndex = $chunkIndex;
		return $result;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->packId = $in->getString();
		$this->chunkIndex = $in->getLInt();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putString($this->packId);
		$out->putLInt($this->chunkIndex);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleResourcePackChunkRequest($this);
	}
}
