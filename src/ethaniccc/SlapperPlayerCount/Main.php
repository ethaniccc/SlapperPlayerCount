<?php

declare(strict_types=1);

namespace ethaniccc\SlapperPlayerCount;

use ethaniccc\SlapperPlayerCount\Tasks\QueryServer;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use slapper\entities\SlapperEntity;
use slapper\events\SlapperCreationEvent;
use slapper\events\SlapperDeletionEvent;
use slapper\SlapperInterface;

class Main extends PluginBase implements Listener {

	private $worldPlayerCount = null;

	/**
	 * @var \ethaniccc\SlapperPlayerCount\SlapperPlayerCountEntityInfo[]
	 */
	private array $trackedSlappers = [];

	public function onEnable() : void {
		/* :eyes: */
		if($this->getConfig()->get("version") !== $this->getDescription()->getVersion()) {
			$this->saveResource("config.yml");
		}

		$updateTicks = (int)$this->getConfig()->get("update_ticks");
		if(!is_integer($updateTicks)) {
			$this->getLogger()->notice("The amount of update ticks is not a whole number and therefore has defaulted to updating every 100 ticks (5 seconds)");
			$updateTicks = 100;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($updateTicks) : void {
			$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($updateTicks) : void {
				$this->updateSlapper();
			}), $updateTicks);

			$this->getServer()->getPluginManager()->registerEvents($this, $this);

			foreach($this->getServer()->getWorldManager()->getWorlds() as $world) {
				foreach($world->getEntities() as $entity) {
					if(!($entity instanceof SlapperEntity)) {
						continue;
					}

					$this->loadTrackedSlapper($entity);
				}
			}

			$wpc = $this->getServer()->getPluginManager()->getPlugin("WorldPlayerCount");
			if($this->getConfig()->get("wpc_support") == false) {
				if($wpc !== null && !$wpc->isDisabled()) {
					$this->getServer()->getPluginManager()->disablePlugin($wpc);
				}
			} elseif($this->getConfig()->get("wpc_support") == true) {
				if($wpc == null || $wpc->isDisabled()) {
					$this->getLogger()->debug("WorldPlayerCount support is enabled, but does not exist (or is disabled) on your server.");
				} else {
					$this->getLogger()->debug("WorldPlayerCount support is enabled, and world querying will depend on it.");
					$this->worldPlayerCount = true;
				}
			}
		}), 100);
	}

	public function updateSlapper() : void {
		$data = [];
		foreach($this->trackedSlappers as $id => $countInfo) {
			$world = $countInfo->getWorld();
			if($world === null) {
				continue;
			}

			$entity = $world->getEntity($id);
			if($entity === null) {
				continue;
			}

			$return = [];
			$this->updateSlapperEntity($entity, $return);
			if(!empty($return)) {
				$data[] = $return;
			}
		}
		if(count($data) > 0) {
			$this->getServer()->getAsyncPool()->submitTask(new QueryServer($data, $this->getConfig()->get("server_online_message"), $this->getConfig()->get("server_offline_message")));
		}
	}

	/**
	 * Update a single slapper entities name tag.
	 *
	 * If an array is provided to $data a task will not be submitted but the data placed in the array.
	 *
	 * @param \pocketmine\entity\Entity $entity
	 * @param array|null                $data
	 */
	public function updateSlapperEntity(Entity $entity, ?array &$data = null) : void {
		$countInfo = $this->trackedSlappers[$entity->getId()];
		if($countInfo === null) {
			return;
		}

		$updateTask = $data === null;
		$type = $countInfo->getType();
		if($type === SlapperPlayerCountEntityInfo::TYPE_SERVER) {
			$data = ["entity" => ["id" => $entity->getId(), "level" => $entity->getWorld()->getFolderName()], "ip" => $countInfo->getIp(), "port" => $countInfo->getPort()];
			if($updateTask) {
				$this->getServer()->getAsyncPool()->submitTask(new QueryServer([$data], $this->getConfig()->get('server_online_message'), $this->getConfig()->get('server_offline_message')));
				$data = [];
			}
		} elseif($type === SlapperPlayerCountEntityInfo::TYPE_WORLD && $this->worldPlayerCount === null) {
			$world = $countInfo->getTargetWorld();
			if($world === null) {
				$world = $this->getServer()->getWorldManager()->getWorldByName($countInfo->getTargetWorldName());
				if(!($world instanceof World)) {
					$lines = explode("\n", $countInfo->getNameTemplate());
					$line = 1;
					foreach($lines as $num => $line) {
						if(strpos($line, "world:") !== false) {
							$line = $num;
							break;
						}
					}
					$lines[$line] = $this->getConfig()->get('world_error_message');
					$entity->setNameTag(implode("\n", $lines));
				}
			}

			$lines = explode("\n", $countInfo->getNameTemplate());
			$line = 1;
			foreach($lines as $num => $line) {
				if(strpos($line, 'world:') !== false) {
					$line = $num;
					break;
				}
			}
			$lines[$line] = str_replace('{playing}', (string)count($world->getPlayers()), $this->getConfig()->get('players_world_message'));
			$entity->setNameTag(implode("\n", $lines));
		}
	}

	private function loadTrackedSlapper(SlapperInterface $entity) : void {
		$countInfo = $this->trackedSlappers[$entity->getId()] ?? null;
		if($countInfo !== null) {
			return;
		}

		$nbt = $entity->namedtag;

		// Check for new player count data
		$countInfo = SlapperPlayerCountEntityInfo::fromNBT($entity->getWorld(), $nbt);
		if($countInfo !== null) {
			$this->trackedSlappers[$entity->getId()] = $countInfo;
			$this->updateSlapperEntity($entity);
			return;
		}

		// Check for old player count data
		$serverString = $nbt->getString('server', '');
		if($serverString !== '') {
			$countInfo = SlapperPlayerCountEntityInfo::fromNameTag($entity->getWorld(), $serverString);
			if($countInfo === null) {
				return;
			}

			$this->trackedSlappers[$entity->getId()] = $countInfo;
			$this->updateSlapperEntity($entity);
			return;
		}
	}

	private function saveTrackedSlapper(SlapperInterface $entity) : void {
		$countInfo = $this->trackedSlappers[$entity->getId()] ?? null;
		if($countInfo === null) {
			return;
		}

		$countInfo->toNBT($entity->namedtag);
	}

	public function onSlapperCreate(SlapperCreationEvent $ev) : void {
		$entity = $ev->getEntity();
		$countInfo = SlapperPlayerCountEntityInfo::fromNameTag($entity->getWorld(), $entity->getNameTag());
		if($countInfo === null) {
			return;
		}

		$this->trackedSlappers[$entity->getId()] = $countInfo;
		$countInfo->toNbt($entity->namedtag);
		$this->updateSlapperEntity($entity);
	}

	public function onSlapperDelete(SlapperDeletionEvent $ev) : void {
		$entity = $ev->getEntity();
		$countInfo = $this->trackedSlappers[$entity->getId()] ?? null;
		if($countInfo === null) {
			return;
		}
		unset($this->trackedSlappers[$entity->getId()]);
	}

	public function onSlapperLoad(EntitySpawnEvent $ev) : void {
		$entity = $ev->getEntity();
		if(!$entity instanceof SlapperInterface) {
			return;
		}

		$this->loadTrackedSlapper($entity);
	}

	public function onSlapperSave(EntityDespawnEvent $ev) : void {
		$entity = $ev->getEntity();
		if(!($entity instanceof SlapperInterface)) {
			return;
		}

		$this->saveTrackedSlapper($entity);
		unset($this->trackedSlappers[$entity->getId()]);
	}

}
