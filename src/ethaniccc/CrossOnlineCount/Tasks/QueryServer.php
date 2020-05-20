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
    private $online_message;
    private $offline_message;

    public function __construct($ip, $port, $entity, string $online_message, string $offline_message){
        $this->ip = $ip;
        $this->port = $port;
        $this->entity = $entity;
        $this->online_message = $online_message;
        $this->offline_message = $offline_message;
    }

    public function onRun(){
        try{
            $online = PMQuery::query($this->ip, $this->port)['Players'];
            $maxonline = PMQuery::query($this->ip, $this->port)['MaxPlayers'];
            $this->online = $online;
            $this->max_online = $maxonline;
        } catch (PmQueryException $e){
            $this->online = -9999;
        }
    }

    public function onCompletion(Server $server){
        $entity = Server::getInstance()->findEntity($this->entity);
        if($entity === null) return;
        if($this->online !== -9999){
            $lines = explode("\n", $entity->getNameTag());
            $base = $this->online_message;
            $base2 = str_replace("{online}", $this->online, $base);
            $message = str_replace("{max_online}", $this->max_online, $base2);
            $lines[1] = $message;
			$nametag = implode("\n", $lines);
			$entity->setNameTag($nametag);
        } else {
            $lines = explode("\n", $entity->getNameTag());
			$lines[1] = $this->offline_message;
			$nametag = implode("\n", $lines);
			$entity->setNameTag($nametag);
        }
    }

}