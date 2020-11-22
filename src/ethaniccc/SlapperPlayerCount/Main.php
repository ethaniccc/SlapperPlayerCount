<?php

declare(strict_types=1);

namespace ethaniccc\SlapperPlayerCount;

use ethaniccc\SlapperPlayerCount\Tasks\InstallSlapper;
use ethaniccc\SlapperPlayerCount\Tasks\QueryServer;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use slapper\entities\SlapperEntity;
use slapper\events\SlapperCreationEvent;
use slapper\events\SlapperDeletionEvent;

class Main extends PluginBase implements Listener
{

    private $worldPlayerCount = null;

    public function onEnable()
    {
        /* :eyes: */
        if ($this->getConfig()->get("version") !== $this->getDescription()->getVersion()) {
            $this->saveResource("config.yml");
        }

        $updateTicks = (int)$this->getConfig()->get("update_ticks");
        if (!is_integer($updateTicks)) {
            $this->getLogger()->notice("The amount of update ticks is not a whole number and therefore has defaulted to updating every 100 ticks (5 seconds)");
            $updateTicks = 100;
        }
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($updateTicks) : void {
            $slapper = $this->getServer()->getPluginManager()->getPlugin("Slapper");
            if ($slapper === null) {
                $this->getLogger()->notice("The Slapper plugin is not installed, we are installing it for you.");
                $this->getLogger()->notice("After the plugin is installed, your server will shutdown - please turn it on again.");
                $this->getServer()->getAsyncPool()->submitTask(new InstallSlapper($this));
            } else {
                $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (int $currentTick) use ($updateTicks) : void {
                    $this->updateSlapper();
                }), $updateTicks);
                $this->getServer()->getPluginManager()->registerEvents($this, $this);
            }
            $wpc = $this->getServer()->getPluginManager()->getPlugin("WorldPlayerCount");
            if ($this->getConfig()->get("wpc_support") == false) {
                if ($wpc !== null && !$wpc->isDisabled()) {
                    $this->getServer()->getPluginManager()->disablePlugin($wpc);
                }
            } elseif ($this->getConfig()->get("wpc_support") == true) {
                if ($wpc == null || $wpc->isDisabled()) {
                    $this->getLogger()->debug("WorldPlayerCount support is enabled, but does not exist (or is disabled) on your server.");
                } else {
                    $this->getLogger()->debug("WorldPlayerCount support is enabled, and world querying will depend on it.");
                    $this->worldPlayerCount = true;
                }
            }
        }), 100);
    }

    public function updateSlapper(): void{
        $data = [];
        foreach ($this->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if (!empty($entity->namedtag->getString("server", ""))) {
                    $server = explode(":", $entity->namedtag->getString("server", ""));
                    if (isset($server[0])) {
                        if ($server[0] === "server") {
                            if (empty($server[1])) $ip = "not_a_valid_ip";
                            else {
                                if (!$this->isValidIP($server[1]) && $this->is_valid_domain_name($server[1])) $ip = "not_a_valid_ip";
                                else $ip = $server[1];
                            }
                            if (empty($server[2])) $port = 0;
                            else {
                                if ($server[2] < 1 || $server[2] > 65536) $port = "invalid_port";
                                else $port = $server[2];
                            }
                            if ($ip !== "not_a_valid_ip" && $port !== "invalid_port"){
                                $data[] = ["entity" => ["id" => $entity->getId(), "level" => $level->getFolderName()], "ip" => $ip, "port" => $port];
                            }
                        } elseif ($server[0] === "world" && $this->worldPlayerCount === null) {
                            if (empty($server[1])) $world = "this_is_an_invalid_world";
                            else $world = $this->getServer()->getLevelByName($server[1]);
                            if ($world === null) $execute = false;
                            else $execute = true;
                            if ($execute) {
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
        if(count($data) > 0){
            $this->getServer()->getAsyncPool()->submitTask(new QueryServer($data, $this->getConfig()->get("server_online_message"), $this->getConfig()->get("server_offline_message")));
        }
    }

    public function isValidIP(string $ip): bool{
        return (preg_match("/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/", $ip) !== false);
    }

    public function is_valid_domain_name(string $domain_name): bool{
        return (preg_match("/([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*:(\d{1,5})/i", $domain_name) //valid chars check
            and preg_match("/.{1,253}/", $domain_name) //overall length check
            and preg_match("/[^\.]{1,63}(\.[^\.]{1,63})*/", $domain_name)); //length of each label
    }

    public function onDisable(){
        foreach ($this->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if (!empty($entity->namedtag->getString("server", ""))) {
                    $lines = explode("\n", $entity->getNameTag());
                    $lines[1] = $entity->namedtag->getString("server", "");
                    $nametag = implode("\n", $lines);
                    $entity->setNameTag($nametag);
                }
            }
        }
    }

    public function onSlapperCreate(SlapperCreationEvent $ev): void{
        $entity = $ev->getEntity();
        $lines = explode("\n", $entity->getNameTag());
        if (isset($lines[1])) $entity->namedtag->setString("server", $lines[1]);
        $this->updateSlapper();
    }

    public function onSlapperDelete(SlapperDeletionEvent $ev): void{
        $entity = $ev->getEntity();
        if (!empty($entity->namedtag->getString("server", ""))) {
            $entity->namedtag->removeTag("server");
        }
    }

}
