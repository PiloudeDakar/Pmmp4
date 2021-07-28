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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\lang\TranslationContainer;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function implode;

class TellCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%" . KnownTranslationKeys::POCKETMINE_COMMAND_TELL_DESCRIPTION,
			"%" . KnownTranslationKeys::COMMANDS_MESSAGE_USAGE,
			["w", "msg"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TELL);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$player = $sender->getServer()->getPlayerByPrefix(array_shift($args));

		if($player === $sender){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%" . KnownTranslationKeys::COMMANDS_MESSAGE_SAMETARGET));
			return true;
		}

		if($player instanceof Player){
			$message = implode(" ", $args);
			$sender->sendMessage(new TranslationContainer(TextFormat::GRAY . TextFormat::ITALIC . "%" . KnownTranslationKeys::COMMANDS_MESSAGE_DISPLAY_OUTGOING, [$player->getDisplayName(), $message]));
			$name = $sender instanceof Player ? $sender->getDisplayName() : $sender->getName();
			$player->sendMessage(new TranslationContainer(TextFormat::GRAY . TextFormat::ITALIC . "%" . KnownTranslationKeys::COMMANDS_MESSAGE_DISPLAY_INCOMING, [$name, $message]));
			Command::broadcastCommandMessage($sender, new TranslationContainer(KnownTranslationKeys::COMMANDS_MESSAGE_DISPLAY_OUTGOING, [$player->getDisplayName(), $message]), false);
		}else{
			$sender->sendMessage(new TranslationContainer(KnownTranslationKeys::COMMANDS_GENERIC_PLAYER_NOTFOUND));
		}

		return true;
	}
}
