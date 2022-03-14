<?php

namespace ethaniccc\SlapperPlayerCount\Tasks;

use libpmquery\PMQuery;
use libpmquery\PmQueryException;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class QueryServer extends AsyncTask {

	private $data;
	private $onlineMsg, $offlineMsg;

	public function __construct(array $data, string $onlineMsg, string $offlineMsg) {
		$this->data = $data;
		$this->onlineMsg = $onlineMsg;
		$this->offlineMsg = $offlineMsg;
	}

	public function onRun() : void {
		$completeData = [];
		foreach($this->data as $data) {
			try {
				$queryData = PMQuery::query($data["ip"], $data["port"]);
				$onlinePlayers = $queryData["Players"];
				$maxOnlinePlayers = $queryData["MaxPlayers"];
				$completeData[] = ["entity" => (array)$data["entity"], "online" => true, "data" => ["online" => $onlinePlayers, "maxOnline" => $maxOnlinePlayers]];
			} catch(PmQueryException $e) {
				$completeData[] = ["entity" => (array)$data["entity"], "online" => false];
			}
		}
		$this->setResult($completeData);
	}

	public function onCompletion() : void {
		$server = Server::getInstance();
		foreach($this->getResult() as $key => $data) {
			$level = $server->getWorldManager()->getWorldByName($data["entity"]["level"]);
			if($level === null) {
				$server->getLogger()->debug("Unexpected null level ($key)");
			} else {
				$entity = $level->getEntity($data["entity"]["id"]);
				if($entity !== null) {
					if($data["online"]) {
						$lines = explode("\n", $entity->getNameTag());
						$base = $this->onlineMsg;
						$message = str_replace(["{online}", "{max_online}"], [$data["data"]["online"], $data["data"]["maxOnline"]], $base);
						$lines[1] = $message;
						$nametag = implode("\n", $lines);
						$entity->setNameTag($nametag);
					} else {
						$lines = explode("\n", $entity->getNameTag());
						$lines[1] = $this->offlineMsg;
						$nametag = implode("\n", $lines);
						$entity->setNameTag($nametag);
					}
				}
			}
		}
	}

}