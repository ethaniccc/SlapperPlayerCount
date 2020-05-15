<?php

namespace ethaniccc\CrossOnlineCount\Tasks;

use libpmquery\PMQuery;
use libpmquery\PmQueryException;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Entity;

class QueryServer extends AsyncTask{

    private $ip; //string
    private $port; //int
    private $entity; //entity
    private $online; //online amount

    public function __construct($ip, $port, $entity){
        $this->ip = $ip;
        $this->port = $port;
        $this->entity = $entity;
    }

    public function onRun(){
        try{
            $online = PMQuery::query($this->ip, $this->port)['Players'];
            $this->online = $online;
        } catch (PmQueryException $e){
            $this->online = -9999;
        }
    }

    public function onCompletion(Server $server){
        $entity = Server::getInstance()->findEntity($this->entity);
        if($entity === null) return;
        if($this->online !== -9999){
            $lines = explode("\n", $entity->getNameTag());
			$lines[1] = TextFormat::YELLOW.$this->online." Online".TextFormat::WHITE;
			$nametag = implode("\n", $lines);
			$entity->setNameTag($nametag);
        } else {
            $lines = explode("\n", $entity->getNameTag());
			$lines[1] = TextFormat::DARK_RED."Server Offline".TextFormat::WHITE;
			$nametag = implode("\n", $lines);
			$entity->setNameTag($nametag);
        }
    }

}