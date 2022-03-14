<?php

declare(strict_types=1);

/**
 * Copyright (C) 2018–2021 NxtLvL Software Solutions
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Jack Noordhuis
 *
 */

namespace ethaniccc\SlapperPlayerCount;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;
use pocketmine\world\World;

class SlapperPlayerCountEntityInfo {

	private ?string $ip = null;
	private ?int $port = null;
	private ?World $world = null;
	private ?string $worldName = null;
	public const TYPE_SERVER = 0;
	public const TYPE_WORLD = 1;

	private function __construct(private int $type, private string $nameTemplate) {

	}

	/**
	 * Construct a new player count instance from a name tag.
	 *
	 * @param string $nameTag
	 *
	 * @return static|null
	 */
	public static function fromNameTag(string $nameTag) : ?self {
		$lines = explode("\n", $nameTag);
		foreach($lines as $line) {
			$parts = explode(':', $line);
			$type = $parts[0] ?? null;
			if($type === null) {
				return null;
			}

			if($type === 'server') {
				$ip = $parts[1] ?? null;
				$port = $parts[2] ?? null;
				if($ip === null or $port === null) {
					return null;
				}

				return self::server($nameTag, $ip, $port);
			} elseif($type === 'world') {
				$name = $parts[1] ?? null;
				if($name === null) {
					return null;
				}

				return self::world($nameTag, $name);
			}
		}

		return null;
	}

	/**
	 * Construct a new server player count instance.
	 *
	 * @param string $nameTemplate
	 * @param string $ip
	 * @param int    $port
	 *
	 * @return static|null
	 */
	public static function server(string $nameTemplate, string $ip, int|string $port) : ?self {
		if(is_string($port)) {
			$port = (int)$port;
			if($port === 0) {
				return null;
			}
		}

		$new = new self(self::TYPE_SERVER, $nameTemplate);

		if(!(self::isValidIP($ip) or self::is_valid_domain_name($ip) or self::isValidPort($port))) {
			return null;
		}

		$new->ip = $ip;
		$new->port = $port;

		return $new;
	}

	protected static function isValidIP(string $ip) : bool {
		return (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/', $ip) !== false);
	}

	protected static function is_valid_domain_name(string $domain_name) : bool {
		return (preg_match('/([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*:(\d{1,5})/i', $domain_name) //valid chars check
			and preg_match('/.{1,253}/', $domain_name) //overall length check
			and preg_match('/[^\.]{1,63}(\.[^\.]{1,63})*/', $domain_name)); //length of each label
	}

	protected static function isValidPort(int $port) : bool {
		return ($port >= 1 and $port <= 65535);
	}

	/**
	 * Construct a new world player count instance.
	 *
	 * @param string                         $nameTemplate
	 * @param \pocketmine\world\World|string $world
	 *
	 * @return static|null
	 */
	public static function world(string $nameTemplate, World|string $world) : ?self {
		$new = new self(self::TYPE_WORLD, $nameTemplate);

		if($world instanceof World) {
			$new->world = $world;
		} else {
			$world = Server::getInstance()->getWorldManager()->getWorldByName($world);
			if(!($world instanceof World)) {
				return null;
			}

			$new->world = $world;
		}

		$new->worldName = $new->world->getFolderName();

		return $new;
	}

	public static function fromNbt(CompoundTag $nbt) : ?self {
		$tag = $nbt->getTag("SlapperPlayerCount");
		if(!($tag instanceof CompoundTag)) {
			return null;
		}

		$type = $tag->getInt("Type");
		$nameTemplate = $tag->getString("NameTemplate");
		if($type === null or $nameTemplate === null) {
			return null;
		}

		if($type === self::TYPE_SERVER) {
			$ip = $tag->getString("Ip");
			$port = $tag->getInt("Port");
			if($ip === null or $port === null) {
				return null;
			}

			return self::server($nameTemplate, $ip, $port);
		} elseif($type === self::TYPE_WORLD) {
			$worldName = $tag->getString("World");
			$world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
			if($world === null) {
				return null;
			}
			return self::world($nameTemplate, $world);
		}

		return null;
	}

	/**
	 * @return int
	 */
	public function getType() : int {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getNameTemplate() : string {
		return $this->nameTemplate;
	}

	/**
	 * @return string|null
	 */
	public function getIp() : ?string {
		return $this->ip;
	}

	/**
	 * @return int|null
	 */
	public function getPort() : ?int {
		return $this->port;
	}

	/**
	 * @return \pocketmine\world\World|null
	 */
	public function getWorld() : ?World {
		return $this->world;
	}

	/**
	 * @return string|null
	 */
	public function getWorldName() : ?string {
		return $this->worldName;
	}

	/**
	 * Save the player count info to an entities NBT tag.
	 *
	 * @param \pocketmine\nbt\tag\CompoundTag $nbt
	 */
	public function toNbt(CompoundTag $nbt) : void {
		$tag = new CompoundTag();
		$tag->setInt("Type", $this->type);
		$tag->setString("NameTemplate", $this->nameTemplate);
		if($this->type === self::TYPE_SERVER) {
			$tag->setString("Ip", $this->ip);
			$tag->setInt("Port", $this->port);
		} elseif($this->type === self::TYPE_WORLD) {
			$tag->setString("World", $this->worldName);
		}

		$nbt->setTag("SlapperPlayerCount", $tag);
	}

}