<?php

namespace ethaniccc\SlapperPlayerCount\Tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

use ethaniccc\CrossOnlineCount\Main;

class InstallSlapper extends AsyncTask{

    private $status;

    public function onRun(){
        $slapper = file_get_contents('https://poggit.pmmp.io/r/85027/Slapper.phar');
        if($slapper === false){
            $this->status = false;
        } else {
            /* I would use Server->getPluginPath(), but this is Async and I'm idot so yeye */
            $path = 'plugins/Slapper.phar';
            $save = file_put_contents($path, $slapper);
            if($save === false){
                $this->status = false;
            } else {
                $this->status = true;
            }
        }
    }

    public function onCompletion(Server $server){
        if($this->status === true){
            Server::getInstance()->getLogger()->notice("Slapper was installed! Shutting down the server...");
            Server::getInstance()->shutdown();
        } else {
            Server::getInstance()->getLogger()->emergency("Slapper failed to install! Disabling plugin...");
            Server::getInstance()->getPluginManager()->disablePlugin(Main::getMain());
        }
    }

}