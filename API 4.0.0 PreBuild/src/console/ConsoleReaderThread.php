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

namespace pocketmine\console;

use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use pocketmine\utils\AssumptionFailedError;
use Webmozart\PathUtil\Path;
use function fgets;
use function fopen;
use function preg_replace;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_select;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function stream_socket_shutdown;
use const PHP_BINARY;
use const STREAM_SHUT_RDWR;

final class ConsoleReaderThread extends Thread{
	private \Threaded $buffer;
	private ?SleeperNotifier $notifier;

	public bool $shutdown = false;

	public function __construct(\Threaded $buffer, ?SleeperNotifier $notifier = null){
		$this->buffer = $buffer;
		$this->notifier = $notifier;
	}

	public function shutdown() : void{
		$this->shutdown = true;
	}

	protected function onRun() : void{
		$buffer = $this->buffer;
		$notifier = $this->notifier;

		/*
		 * This pile of shit exists because PHP on Windows is broken, and can't handle stream_select() on stdin or pipes
		 * properly - stdin native triggers stream_select() when a key is pressed, causing it to get stuck in fgets()
		 * waiting for a line that might never come (and Windows doesn't support character-based reading either), and
		 * pipes just constantly trigger stream_select() instead of only when data is returned, rendering it useless.
		 *
		 * This results in whichever process reads stdin getting stuck on shutdown, which previously forced us to kill
		 * the entire server process to make it go away.
		 *
		 * To get around this problem, we delegate the responsibility of reading stdin to a subprocess, which we can
		 * then brutally murder when the server shuts down, without killing the entire server process.
		 * Thankfully, stream_select() actually works properly on sockets, so we can use them for inter-process
		 * communication.
		 */

		$server = stream_socket_server("tcp://127.0.0.1:0");
		if($server === false){
			throw new \RuntimeException("Failed to open console reader socket server");
		}
		$address = stream_socket_get_name($server, false);
		if($address === false) throw new AssumptionFailedError("stream_socket_get_name() shouldn't return false here");

		$sub = proc_open(
			[PHP_BINARY, '-r', sprintf('require "%s";', Path::join(__DIR__, 'ConsoleReaderChildProcess.php')), $address],
			[
				2 => fopen("php://stderr", "w"),
			],
			$pipes
		);
		if($sub === false){
			throw new AssumptionFailedError("Something has gone horribly wrong");
		}
		$client = stream_socket_accept($server);
		if($client === false){
			throw new AssumptionFailedError("stream_socket_accept() returned false");
		}
		stream_socket_shutdown($server, STREAM_SHUT_RDWR);
		while(!$this->shutdown){
			$r = [$client];
			$w = null;
			$e = null;
			if(stream_select($r, $w, $e, 0, 200000) === 1){
				$command = fgets($client);
				if($command === false){
					throw new AssumptionFailedError("Something has gone horribly wrong");
				}

				$buffer[] = preg_replace("#\\x1b\\x5b([^\\x1b]*\\x7e|[\\x40-\\x50])#", "", $command);
				if($notifier !== null){
					$notifier->wakeupSleeper();
				}
			}
		}

		//we have no way to signal to the subprocess to shut down gracefully; besides, Windows sucks, and the subprocess
		//gets stuck in a blocking fgets() read because stream_select() is a hunk of junk (hence the separate process in
		//the first place).
		proc_terminate($sub);
		stream_socket_shutdown($client, STREAM_SHUT_RDWR);
	}

	public function getThreadName() : string{
		return "Console";
	}
}
