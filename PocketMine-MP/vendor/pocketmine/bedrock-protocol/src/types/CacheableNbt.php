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

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;

/**
 * @phpstan-template TTagType of Tag
 */
final class CacheableNbt{

	/** @phpstan-var TTagType */
	private Tag $root;
	private ?string $encodedNbt;

	/**
	 * @phpstan-param TTagType $nbtRoot
	 */
	public function __construct(Tag $nbtRoot){
		$this->root = $nbtRoot;
	}

	/**
	 * @phpstan-return TTagType
	 */
	public function getRoot() : Tag{
		return $this->root;
	}

	public function getEncodedNbt() : string{
		return $this->encodedNbt ?? ($this->encodedNbt = (new NetworkNbtSerializer())->write(new TreeRoot($this->root)));
	}
}