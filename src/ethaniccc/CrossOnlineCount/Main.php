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
        var_dump($this->getConfig()->get("wpc_support"));

        /* The main goal right now is to actually
        implement the config correctly. */

        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");

        $updateTicks = (int) $this->getConfig()->get("update_ticks");
        if(!is_integer($updateTicks)){
            $this->getLogger()->notice("The amount of update ticks is not a whole number and therefore has defaulted to updating every 100 ticks (5 seconds)");
            $updateTicks = 100;
        }
        self::$instance = $this;
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use($updateTicks) : void{
               $slapper = $this->getServer()->getPluginManager()->getPlugin("Slapper");
               if($slapper === null){
                   $this->getLogger()->notice("The Slapper plugin is not installed, we are installing it for you.");
                   $this->getLogger()->notice("After the plugin is installed, your server will shutdown - please turn it on again.");
                   $this->getServer()->getAsyncPool()->submitTask(new InstallSlapper());
                } else {
                   /* Update server details every 1 second. */
                   /* TODO: Make this configurable in the config! */
                   $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick) use($updateTicks) : void{
                      $this->updateSlapper();
                   }), $updateTicks);
                   $this->getServer()->getPluginManager()->registerEvents($this, $this);
               }
               $wpc = $this->getServer()->getPluginManager()->getPlugin("WorldPlayerCount");
               if($wpc == null) return;
               if($wpc->isDisabled()) return;
               if($this->getConfig()->get("wpc_support") != true)
               $this->getLogger()->notice("WorldPlayerCount support is disabled in the config. Please enable it if you want to use WPC alongside SlapperPlayerCount.");
               $this->getServer()->getPluginManager()->disablePlugin($wpc);
               if($this->getConfig()->get("wpc_support") == true){
                   if($wpc !== null || !$wpc->isDisabled()){
                    $this->getLogger()->notice("WorldPlayerCount support is enabled, but does not exist on your server.");
                   } else {
                        
                   }
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

    public static function getMain() : Main{
        return self::$instance;
    }

    public function updateSlapper() : void{
      foreach($this->getServer()->getLevels() as $level) {
			  foreach($level->getEntities() as $entity) {
				  if(!empty($entity->namedtag->getString("server", ""))) {
                    $server = explode(":", $entity->namedtag->getString("server", ""));
                    if(isset($server[0])){
                        if($server[0] === "server"){
                            if(empty($server[1])) $ip = "not_a_valid_ip";
                            else{
                                if(!$this->isValidIP($server[1]) && $this->is_valid_domain_name($server[1])) $ip = "not_a_valid_ip";
                                else $ip = $server[1];
                            }
                            if(empty($server[2])) $port = 0;
                            else{
                                if($server[2] < 1 || $server[2] > 65536) $port = "invalid_port";
                                else $port = $server[2];
                            }
                            if($ip !== "not_a_valid_ip" && $port !== "invalid_port") $this->getServer()->getAsyncPool()->submitTask(new QueryServer($ip, $port, $entity->getId(), $this->getConfig()->get("server_online_message"), $this->getConfig()->get("server_offline_message")));
                        } elseif($server[0] === "world"){
                            if(empty($server[1])) $world = "this_is_an_invalid_world";
                            else $world = $this->getServer()->getLevelByName($server[1]);
                            if($world === null) $execute = false;
                            else $execute = true;
                            if($execute){
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
                        }
                    }
				}
			}
		}
    }

    public function onSlapperCreate(SlapperCreationEvent $ev) : void{
	  	$entity = $ev->getEntity();
        $lines = explode("\n", $entity->getNameTag());
        if(isset($lines[1])) $entity->namedtag->setString("server", $lines[1]);
		$this->updateSlapper();
    }
    
    public function onSlapperDelete(SlapperDeletionEvent $ev) : void{
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
