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

	public const TYPE_SERVER = 0;
	public const TYPE_WORLD = 1;

	/**
	 * Construct a new server player count instance.
	 *
	 * @param \pocketmine\world\World $world
	 * @param string                  $nameTemplate
	 * @param string                  $ip
	 * @param int|string              $port
	 *
	 * @return static|null
	 */
	public static function server(World $world, string $nameTemplate, string $ip, int|string $port) : ?self {
		if(is_string($port)) {
			$port = (int)$port;
			if($port === 0) {
				return null;
			}
		}

		$new = new self(self::TYPE_SERVER, $world, $nameTemplate);

		if(!(self::isValidIP($ip) or self::is_valid_domain_name($ip) or self::isValidPort($port))) {
			return null;
		}

		$new->ip = $ip;
		$new->port = $port;

		return $new;
	}

	/**
	 * Construct a new world player count instance.
	 *
	 * @param string                         $nameTemplate
	 * @param \pocketmine\world\World|string $world
	 *
	 * @return static|null
	 */
	public static function world(World $world, string $nameTemplate, World|string $targetWorld) : ?self {
		$new = new self(self::TYPE_WORLD, $world, $nameTemplate);

		if($targetWorld instanceof World) {
			$new->targetWorld = $targetWorld;
		} else {
			$targetWorld = Server::getInstance()->getWorldManager()->getWorldByName($targetWorld);
			if(!($targetWorld instanceof World)) {
				return null;
			}

			$new->targetWorld = $targetWorld;
		}

		$new->targetWorldName = $targetWorld->getFolderName();

		return $new;
	}

	/**
	 * Construct a new player count instance from a name tag.
	 *
	 * @param \pocketmine\world\World $world
	 * @param string                  $nameTag
	 *
	 * @return static|null
	 */
	public static function fromNameTag(World $world, string $nameTag) : ?self {
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

				return self::server($world, $nameTag, $ip, $port);
			} elseif($type === 'world') {
				$name = $parts[1] ?? null;
				if($name === null) {
					return null;
				}

				return self::world($world, $nameTag, $name);
			}
		}

		return null;
	}

	public static function fromNbt(World $world, CompoundTag $nbt) : ?self {
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

			return self::server($world, $nameTemplate, $ip, $port);
		} elseif($type === self::TYPE_WORLD) {
			$worldName = $tag->getString("World");
			$world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
			if($world === null) {
				return null;
			}
			return self::world($world, $nameTemplate, $world);
		}

		return null;
	}

	private string $worldName;

	private ?string $ip = null;
	private ?int $port = null;

	private ?World $targetWorld = null;
	private ?string $targetWorldName = null;

	private function __construct(private int $type, private World $world, private string $nameTemplate) {
		$this->worldName = $world->getFolderName();
	}

	public function getType() : int {
		return $this->type;
	}

	public function getWorld() : World {
		return $this->world;
	}

	public function getWorldName() : string {
		return $this->worldName;
	}

	public function getNameTemplate() : string {
		return $this->nameTemplate;
	}

	public function getIp() : ?string {
		return $this->ip;
	}

	public function getPort() : ?int {
		return $this->port;
	}

	public function getTargetWorld() : ?World {
		return $this->targetWorld;
	}

	public function getTargetWorldName() : ?string {
		return $this->targetWorldName;
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
			$tag->setString("World", $this->targetWorldName);
		}

		$nbt->setTag("SlapperPlayerCount", $tag);
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

}