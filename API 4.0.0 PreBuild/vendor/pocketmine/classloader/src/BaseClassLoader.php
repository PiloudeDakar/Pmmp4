<?php

/*
 * PocketMine Standard PHP Library
 * Copyright (C) 2014-2018 PocketMine Team <https://github.com/PocketMine/PocketMine-SPL>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

class BaseClassLoader implements DynamicClassLoader{

	/*
	 * Note for future maintainers: This class doesn't need to extend `Threaded` because of pthreads' serialization
	 * trickery - the lookup Threaded objects will be shared between threads after deserialization, even though the
	 * actual BaseClassLoader objects will be distinct (not shared).
	 * It's done this way to bypass useless extra locking - since the BaseClassLoader itself doesn't have any
	 * non-Threaded fields, it doesn't make any difference whether it's Threaded or not, except for performance.
	 */

	/** @var \Threaded|string[] */
	private $fallbackLookup;
	/** @var \Threaded|string[][] */
	private $psr4Lookup;

	public function __construct(){
		$this->fallbackLookup = new \Threaded;
		$this->psr4Lookup = new \Threaded;
	}

	protected function normalizePath(string $path) : string{
		$parts = explode("://", $path, 2);
		if(count($parts) === 2){
			return $parts[0] . "://" . str_replace('/', DIRECTORY_SEPARATOR, $parts[1]);
		}
		return str_replace('/', DIRECTORY_SEPARATOR, $parts[0]);
	}

	/**
	 * Adds a path to the lookup list
	 *
	 * @param string $namespacePrefix An empty string, or string ending with a backslash
	 * @param string $path
	 * @param bool   $prepend
	 *
	 * @return void
	 */
	public function addPath(string $namespacePrefix, $path, $prepend = false){
		$path = $this->normalizePath($path);
		if($namespacePrefix === '' || $namespacePrefix === '\\'){
			$this->fallbackLookup->synchronized(function() use ($path, $prepend) : void{
				$this->appendOrPrependLookupEntry($this->fallbackLookup, $path, $prepend);
			});
		}else{
			$namespacePrefix = trim($namespacePrefix, '\\') . '\\';
			$this->psr4Lookup->synchronized(function() use ($namespacePrefix, $path, $prepend) : void{
				$list = $this->psr4Lookup[$namespacePrefix] ?? null;
				if($list === null){
					$list = $this->psr4Lookup[$namespacePrefix] = new \Threaded;
				}
				$this->appendOrPrependLookupEntry($list, $path, $prepend);
			});
		}
	}

	protected function appendOrPrependLookupEntry(\Threaded $list, string $entry, bool $prepend) : void{
		if($prepend){
			$entries = $this->getAndRemoveLookupEntries($list);
			$list[] = $entry;
			foreach($entries as $removedEntry){
				$list[] = $removedEntry;
			}
		}else{
			$list[] = $entry;
		}
	}

	/**
	 * @return string[]
	 */
	protected function getAndRemoveLookupEntries(\Threaded $list){
		$entries = [];
		while($list->count() > 0){
			$entries[] = $list->shift();
		}
		return $entries;
	}

	/**
	 * Attaches the ClassLoader to the PHP runtime
	 *
	 * @param bool $prepend
	 *
	 * @return bool
	 */
	public function register($prepend = false){
		return spl_autoload_register(function(string $name) : void{
			$this->loadClass($name);
		}, true, $prepend);
	}

	/**
	 * Called when there is a class to load
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function loadClass($name){
		$path = $this->findClass($name);
		if($path !== null){
			include($path);
			if(!class_exists($name, false) and !interface_exists($name, false) and !trait_exists($name, false)){
				return false;
			}

			if(method_exists($name, "onClassLoaded") and (new ReflectionClass($name))->getMethod("onClassLoaded")->isStatic()){
				$name::onClassLoaded();
			}

			return true;
		}

		return false;
	}

	/**
	 * Returns the path for the class, if any
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findClass($name){
		$baseName = str_replace("\\", DIRECTORY_SEPARATOR, $name);

		foreach($this->fallbackLookup as $path){
			$filename = $path . DIRECTORY_SEPARATOR . $baseName . ".php";
			if(file_exists($filename)){
				return $filename;
			}
		}

		// PSR-4 lookup
		$logicalPathPsr4 = $baseName . ".php";

		return $this->psr4Lookup->synchronized(function() use ($name, $logicalPathPsr4) : ?string{
			$subPath = $name;
			while(false !== $lastPos = strrpos($subPath, '\\')){
				$subPath = substr($subPath, 0, $lastPos);
				$search = $subPath . '\\';

				if(isset($this->psr4Lookup[$search])){
					$pathEnd = DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $lastPos + 1);
					foreach($this->psr4Lookup[$search] as $dir){
						if(file_exists($file = $dir . $pathEnd)){
							return $file;
						}
					}
				}
			}
			return null;
		});
	}
}
