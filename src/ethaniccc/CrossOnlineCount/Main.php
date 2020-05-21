<?php

declare(strict_types=1);

namespace ethaniccc\CrossOnlineCount;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\Internet;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\level\Level;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\scheduler\TaskHandler;
use libpmquery\PMQuery;
use libpmquery\PmQueryException;
use slapper\events\SlapperCreationEvent;
use slapper\events\SlapperDeletionEvent;
use slapper\entities\SlapperEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use ethaniccc\CrossOnlineCount\Tasks\InstallSlapper;
use ethaniccc\CrossOnlineCount\Tasks\QueryServer;

class Main extends PluginBase implements Listener{

    private static $instance;

    public function onEnable(){
        self::$instance = $this;
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) : void{
               $slapper = $this->getServer()->getPluginManager()->getPlugin("Slapper");
               if($slapper === null){
                   $this->getServer()->getAsyncPool()->submitTask(new InstallSlapper());
                   $this->getLogger()->notice("The Slapper plugin is not installed, we are installing it for you.");
                   $this->getLogger()->notice("After the plugin is installed, your server will shutdown - please turn it on again.");
               } else {
                   /* Update server details every 0.25 seconds. */
                   $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick) : void{
                       $this->updateSlapper();
                   }), 5);
                   $this->getServer()->getPluginManager()->registerEvents($this, $this);
               }
        }), 100);
    }

    public function onDisable(){
      foreach($this->getServer()->getLevels() as $level) {
			  foreach($level->getEntities() as $entity) {
				  if(!empty($entity->namedtag->getString("server", ""))) {
					  $lines = explode("\n", $entity->getNameTag());
					  $lines[1] = $entity->namedtag->getString("server", "");
					  $nametag = implode("\n", $lines);
					  $entity->setNameTag($nametag);
				  }
			  }
		  }
    }

    public static function getMain() {
        return self::$instance;
    }

    public function updateSlapper(){
      foreach($this->getServer()->getLevels() as $level) {
			  foreach($level->getEntities() as $entity) {
				  if(!empty($entity->namedtag->getString("server", ""))) {
                    $server = explode(":", $entity->namedtag->getString("server", ""));
                    if(isset($server[0])){
                        switch($server[0]){
                            case "server":
                                if(!isset($server[1])) $ip = 0;
                                else $ip = $server[1];
                                if(!isset($server[2])) $port = 19132;
                                else $port = $server[2];
                                if($ip == 0) $do = false;
                                else $do = true;
                                if($do === true) $this->getServer()->getAsyncPool()->submitTask(new QueryServer($ip, $port, $entity->getId(), $this->getConfig()->get("server_online_message"), $this->getConfig()->get("server_offline_message")));
                            break;
                            case "world":
                                if(!isset($server[1])) $world = "aa46b8ednonono";
                                else $world = $this->getServer()->getLevelByName($server[1]);
                                if($world === "aa46b8ednonono" || $world === null) $do = false;
                                else $do = true;
                                if($do === true){
                                    $lines = explode("\n", $entity->getNameTag());
                                    $base = $this->getConfig()->get("players_world_message");
                                    $message = str_replace("{playing}", count($world->getPlayers()), $base);
                                    $lines[1] = $message;
                                    $nametag = implode("\n", $lines);
			                        $entity->setNameTag($nametag);
                                } else {
                                    $lines = explode("\n", $entity->getNameTag());
                                    $message = $this->getConfig()->get("world_error_message");
                                    $lines[1] = $message;
                                    $nametag = implode("\n", $lines);
			                        $entity->setNameTag($nametag);
                                }
                            break;
                            default:

                            break;
                        }
                    }
				  }
			  }
		  }
    }

    public function onHitSlapper(EntityDamageByEntityEvent $event){
        $entity = $event->getEntity();
        $player = $event->getDamager();
        if($entity instanceof SlapperEntity){

            $nametag = explode("\n", $entity->getNameTag());

            if(!isset($nametag[1])) return;

            if($nametag[1] == $this->getConfig()->get("server_offline_message")){
                if($this->getConfig()->get("server_offline_knockback") == true){
                    $motion = $player->subtract($entity);
                    $player->knockBack($entity, 0, $motion->x, $moition->z, $this->getConfig()->get("server_offline_knockback_amount"));
                }
            } 
            
            if($nametag[1] == $this->getConfig()->get("world_error_message")){
                if($this->getConfig()->get("world_nofind_knockback") == true){
                    $motion = $player->subtract($entity);
                    $player->knockBack($entity, 0, $motion->x, $moition->z, $this->getConfig()->get("world_nofind_knockback_amount"));
                }
            }

        }
    }

    public function onSlapperCreate(SlapperCreationEvent $ev) {
	  	$entity = $ev->getEntity();
        $lines = explode("\n", $entity->getNameTag());
        if(isset($lines[1])) $entity->namedtag->setString("server", $lines[1]);
		$this->updateSlapper();
    }
    
    public function onSlapperDelete(SlapperDeletionEvent $ev) {
		  $entity = $ev->getEntity();
		  if(!empty($entity->namedtag->getString("server", ""))) {
			  $entity->namedtag->removeTag("server");
		  }
    }

    public function is_valid_domain_name(string $domain_name) {
		  return (preg_match("/([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*:(\d{1,5})/i", $domain_name) //valid chars check
		  and preg_match("/.{1,253}/", $domain_name) //overall length check
		  and preg_match("/[^\.]{1,63}(\.[^\.]{1,63})*/", $domain_name)); //length of each label
    }
    
    public function isValidIP(string $ip) {
		  return (preg_match("/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/", $ip) !== false);
	  }

}
