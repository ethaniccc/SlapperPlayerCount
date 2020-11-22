<?php

namespace ethaniccc\SlapperPlayerCount\Tasks;

use ethaniccc\SlapperPlayerCount\Main;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class InstallSlapper extends AsyncTask{

    private $status;

    public function __construct(Main $main){
        $this->storeLocal($main);
    }

    public function onRun(){
        /*
        $arrContextOptions = array(
            "ssl "=> array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        $slapper = file_get_contents('https://poggit.pmmp.io/r/85027/Slapper.phar', false, stream_context_create($arrContextOptions));
        if ($slapper === false) {
            $this->status = false;
        } else {
            $path = 'plugins/Slapper.phar';
            $save = file_put_contents($path, $slapper);
            if ($save === false) {
                $this->status = false;
            } else {
                $this->status = true;
            }
        }
        */
        // slapper is not updated yet
        $this->status = false;
    }

    public function onCompletion(Server $server){
        if ($this->status) {
            $server->getLogger()->notice("Slapper was installed! Shutting down the server...");
            $server->shutdown();
        } else {
            $server->getLogger()->emergency("At this time (11/22/2020), no updated copy of slapper was found, you must get a copy of Slapper manually (https://poggit.pmmp.io/p/Slapper)");
            $server->getLogger()->emergency("Slapper failed to install! Disabling plugin...");
            $server->getPluginManager()->disablePlugin($this->fetchLocal());
        }
    }

}