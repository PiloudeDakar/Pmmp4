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
use pocketmine\network\mcpe\protocol\types\CacheableNbt;

class AddVolumeEntityPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::ADD_VOLUME_ENTITY_PACKET;

	private int $entityNetId;
	/** @phpstan-var CacheableNbt<\pocketmine\nbt\tag\CompoundTag> */
	private CacheableNbt $data;
	private string $engineVersion;

	/**
	 * @generate-create-func
	 * @phpstan-param CacheableNbt<\pocketmine\nbt\tag\CompoundTag> $data
	 */
	public static function create(int $entityNetId, CacheableNbt $data, string $engineVersion) : self{
		$result = new self;
		$result->entityNetId = $entityNetId;
		$result->data = $data;
		$result->engineVersion = $engineVersion;
		return $result;
	}

	public function getEntityNetId() : int{ return $this->entityNetId; }

	/** @phpstan-return CacheableNbt<\pocketmine\nbt\tag\CompoundTag> */
	public function getData() : CacheableNbt{ return $this->data; }

	public function getEngineVersion() : string{ return $this->engineVersion; }

	protected function decodePayload(PacketSerializer $in) : void{
		$this->entityNetId = $in->getUnsignedVarInt();
		$this->data = new CacheableNbt($in->getNbtCompoundRoot());
		$this->engineVersion = $in->getString();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt($this->entityNetId);
		$out->put($this->data->getEncodedNbt());
		$out->putString($this->engineVersion);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleAddVolumeEntity($this);
	}
}
