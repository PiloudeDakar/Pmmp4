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

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use function count;

class StartGamePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::START_GAME_PACKET;

	public int $actorUniqueId;
	public int $actorRuntimeId;
	public int $playerGamemode;

	public Vector3 $playerPosition;

	public float $pitch;
	public float $yaw;

	public LevelSettings $levelSettings;

	public string $levelId = ""; //base64 string, usually the same as world folder name in vanilla
	public string $worldName;
	public string $premiumWorldTemplateId = "";
	public bool $isTrial = false;
	public PlayerMovementSettings $playerMovementSettings;
	public int $currentTick = 0; //only used if isTrial is true
	public int $enchantmentSeed = 0;
	public string $multiplayerCorrelationId = ""; //TODO: this should be filled with a UUID of some sort
	public bool $enableNewInventorySystem = false; //TODO
	public string $serverSoftwareVersion;

	/**
	 * @var BlockPaletteEntry[]
	 * @phpstan-var list<BlockPaletteEntry>
	 */
	public array $blockPalette = [];

	/**
	 * Checksum of the full block palette. This is a hash of some weird stringified version of the NBT.
	 * This is used along with the baseGameVersion to check for inconsistencies in the block palette.
	 * Fill with 0 if you don't want to bother having the client verify the palette (seems pointless anyway).
	 */
	public int $blockPaletteChecksum;

	/**
	 * @var ItemTypeEntry[]
	 * @phpstan-var list<ItemTypeEntry>
	 */
	public array $itemTable;

	/**
	 * @generate-create-func
	 * @param BlockPaletteEntry[] $blockPalette
	 * @param ItemTypeEntry[]     $itemTable
	 * @phpstan-param list<BlockPaletteEntry> $blockPalette
	 * @phpstan-param list<ItemTypeEntry>     $itemTable
	 */
	public static function create(
		int $actorUniqueId,
		int $actorRuntimeId,
		int $playerGamemode,
		Vector3 $playerPosition,
		float $pitch,
		float $yaw,
		LevelSettings $levelSettings,
		string $levelId,
		string $worldName,
		string $premiumWorldTemplateId,
		bool $isTrial,
		PlayerMovementSettings $playerMovementSettings,
		int $currentTick,
		int $enchantmentSeed,
		string $multiplayerCorrelationId,
		bool $enableNewInventorySystem,
		string $serverSoftwareVersion,
		array $blockPalette,
		int $blockPaletteChecksum,
		array $itemTable,
	) : self{
		$result = new self;
		$result->actorUniqueId = $actorUniqueId;
		$result->actorRuntimeId = $actorRuntimeId;
		$result->playerGamemode = $playerGamemode;
		$result->playerPosition = $playerPosition;
		$result->pitch = $pitch;
		$result->yaw = $yaw;
		$result->levelSettings = $levelSettings;
		$result->levelId = $levelId;
		$result->worldName = $worldName;
		$result->premiumWorldTemplateId = $premiumWorldTemplateId;
		$result->isTrial = $isTrial;
		$result->playerMovementSettings = $playerMovementSettings;
		$result->currentTick = $currentTick;
		$result->enchantmentSeed = $enchantmentSeed;
		$result->multiplayerCorrelationId = $multiplayerCorrelationId;
		$result->enableNewInventorySystem = $enableNewInventorySystem;
		$result->serverSoftwareVersion = $serverSoftwareVersion;
		$result->blockPalette = $blockPalette;
		$result->blockPaletteChecksum = $blockPaletteChecksum;
		$result->itemTable = $itemTable;
		return $result;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->actorUniqueId = $in->getActorUniqueId();
		$this->actorRuntimeId = $in->getActorRuntimeId();
		$this->playerGamemode = $in->getVarInt();

		$this->playerPosition = $in->getVector3();

		$this->pitch = $in->getLFloat();
		$this->yaw = $in->getLFloat();

		$this->levelSettings = LevelSettings::read($in);

		$this->levelId = $in->getString();
		$this->worldName = $in->getString();
		$this->premiumWorldTemplateId = $in->getString();
		$this->isTrial = $in->getBool();
		$this->playerMovementSettings = PlayerMovementSettings::read($in);
		$this->currentTick = $in->getLLong();

		$this->enchantmentSeed = $in->getVarInt();

		$this->blockPalette = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$blockName = $in->getString();
			$state = $in->getNbtCompoundRoot();
			$this->blockPalette[] = new BlockPaletteEntry($blockName, new CacheableNbt($state));
		}

		$this->itemTable = [];
		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$stringId = $in->getString();
			$numericId = $in->getSignedLShort();
			$isComponentBased = $in->getBool();

			$this->itemTable[] = new ItemTypeEntry($stringId, $numericId, $isComponentBased);
		}

		$this->multiplayerCorrelationId = $in->getString();
		$this->enableNewInventorySystem = $in->getBool();
		$this->serverSoftwareVersion = $in->getString();
		$this->blockPaletteChecksum = $in->getLLong();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putActorUniqueId($this->actorUniqueId);
		$out->putActorRuntimeId($this->actorRuntimeId);
		$out->putVarInt($this->playerGamemode);

		$out->putVector3($this->playerPosition);

		$out->putLFloat($this->pitch);
		$out->putLFloat($this->yaw);

		$this->levelSettings->write($out);

		$out->putString($this->levelId);
		$out->putString($this->worldName);
		$out->putString($this->premiumWorldTemplateId);
		$out->putBool($this->isTrial);
		$this->playerMovementSettings->write($out);
		$out->putLLong($this->currentTick);

		$out->putVarInt($this->enchantmentSeed);

		$out->putUnsignedVarInt(count($this->blockPalette));
		foreach($this->blockPalette as $entry){
			$out->putString($entry->getName());
			$out->put($entry->getStates()->getEncodedNbt());
		}

		$out->putUnsignedVarInt(count($this->itemTable));
		foreach($this->itemTable as $entry){
			$out->putString($entry->getStringId());
			$out->putLShort($entry->getNumericId());
			$out->putBool($entry->isComponentBased());
		}

		$out->putString($this->multiplayerCorrelationId);
		$out->putBool($this->enableNewInventorySystem);
		$out->putString($this->serverSoftwareVersion);
		$out->putLLong($this->blockPaletteChecksum);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleStartGame($this);
	}
}
