<?php

namespace dadodasyra\dnsmcpe;

use Closure;
use dadodasyra\dnsmcpe\formapi\jojoe77777\FormAPI\CustomForm;
use dadodasyra\dnsmcpe\formapi\jojoe77777\FormAPI\SimpleForm;
use dadodasyra\dnsmcpe\libasynql\DataConnector;
use dadodasyra\dnsmcpe\libasynql\libasynql;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class dns_mcpe extends PluginBase implements Listener
{
    public array $current = [];
    private array $default = ["! Histeria" => ["ip" => "histeria.fr", "port" => 19132]];
    private static DataConnector $database;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        self::$database = libasynql::create($this, ["type" => "mysql", "worker-limit" => 1,
            "mysql" => ["host" => "127.0.0.1", "username" => "dns", "password" => "@)B{4Tv?Ykp58Dq9", "schema" => "dns"]],
            ["mysql" => "mysql.sql"]);
        self::$database->executeGeneric("init");
    }

    public function onquit(PlayerQuitEvent $event)
    {
        if (isset($this->current[$event->getPlayer()->getName()]))
            unset($this->current[$event->getPlayer()->getName()]);
    }

    public function joinevent(PlayerJoinEvent $event)
    {
        $p = $event->getPlayer();
        $this->firstopen($p);
        $p->setImmobile(true);
        $p->teleport($p, $p->getYaw(), 90);
    }

    public function firstopen(Player $player)
    {
        $funcdb = function (array $rows) use ($player) {
            foreach ($rows as $row) {
                $this->current[$row["player"]][$row["server"]] = ["ip" => $row["ip"], "port" => $row["port"]];
            }
            $this->sendfirstform($player);
        };
        self::$database->executeSelectRaw("SELECT * FROM `players` WHERE `player` = '" . $player->getName() . "';", [], $funcdb);
    }

    public function sendfirstform(Player $player)
    {
        $form = new SimpleForm($this->funcform());
        $form->setTitle("§6Server Chooser");
        $form->addButton("§6Manage servers", -1, "", "manage");
        if (empty($this->current[$pname = $player->getName()])) {
            foreach ($this->default as $server => $default) {
                $this->current[$pname][$server] = ["ip" => $ip = $default["ip"], "port" => $port = $default["port"]];
                self::$database->executeInsertRaw("INSERT INTO `players` VALUES ('$pname', '$server', '$ip', '$port');");
            }
        }
        foreach ($this->current[$pname] as $server => $details) {
            $ip = $details["ip"];
            $port = $details["port"];
            $status = json_decode(file_get_contents("https://api.mcsrvstat.us/bedrock/2/$ip:$port", false,
                stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]])));

            if (!$status->debug->query === true or !$status->online === true) {
                $form->addButton("§c" . $server, -1, "", $server);
            } else {
                $form->addButton("§a" . $server." §7".$status->players->online."/".$status->players->max, -1, "", $server);
            }
        }
        $form->sendToPlayer($player);
    }

    public function funcform(): Closure
    {
        return function (Player $player, $data) {
            if (!isset($data)) {
                $this->sendfirstform($player);
                return;
            }
            $server = $data;
            $pname = $player->getName();

            if ($data === "manage") {
                $this->openmanage($player);
            } else {
                $ip = ($array = $this->current[$pname][$server])["ip"];
                $port = $array["port"];
                $player->transfer($ip, (int)$port);
                $this->getLogger()->info("$pname -> $ip:$port");
            }
        };
    }

    public function openmanage(Player $player)
    {
        $form = new SimpleForm($this->funcformmanage());
        $form->setTitle("§6Manage servers");
        $form->addButton("§aAdd", -1, "", "add");

        foreach ($this->current[$player->getName()] as $server => $datas) {
            $form->addButton("§e" . $server, -1, "", $server);
        }
        $form->addButton("§cBack", -1, "", "back");
        $form->sendToPlayer($player);
    }

    public function funcformmanage(): Closure
    {
        return function (Player $player, $data) {
            if (!isset($data) || $data === "back") {
                $this->sendfirstform($player);
                return;
            }
            if ($data === "add") {
                $this->openAddForm($player);
                return;
            }
            $server = $data;
            $ip = ($array = $this->current[$player->getName()][$server])["ip"];
            $port = $array["port"];
            $form = new SimpleForm($this->funcformmanageserver($server, $ip, $port));
            $form->setTitle("§6Edit $server");
            $form->setContent("§a$server : §e" . $ip . ":" . $port);
            $form->addButton("§cDelete", -1, "", "delete");
            $form->addButton("§cEdit", -1, "", "edit");
            $form->addButton("§cBack", -1, "", "back");
            $form->sendToPlayer($player);
        };
    }

    public function openAddForm(Player $player)
    {
        $form = new CustomForm($this->funcformmanageadd());
        $form->setTitle("§6Edit server");
        $form->addInput("Server Name", "Histeria", "", "server");
        $form->addInput("Server IP", "histeria.fr", "", "ip");
        $form->addInput("Server Port", "19132", "19132", "port");
        $form->sendToPlayer($player);
    }

    public function funcformmanageserver($server, $ip, $port): Closure
    {
        return function (Player $player, $data) use ($server, $ip, $port) {
            if (!isset($data)) {
                $this->sendfirstform($player);
                return;
            }
            if ($data === "delete") {
                unset($this->current[$player->getName()][$server]);
                self::$database->executeGenericRaw("DELETE FROM `players` WHERE `player` = '" . $player->getName() . "' AND `server` = '" . $server . "'");
                $this->successfullform($player, function (Player $player, $data) {
                    $this->sendfirstform($player);
                });
            } else if ($data === "edit") {
                $form = new CustomForm($this->funcformmanageserveredit($server, $ip, $port));
                $form->setTitle("§6Edit server");
                $form->addInput("Server Name", $server, $server, "server");
                $form->addInput("Server IP", $ip, $ip, "ip");
                $form->addInput("Server Port", $port, $port, "port");
                $form->sendToPlayer($player);
            } else if ($data === "back") {
                $this->openmanage($player);
            }
        };
    }

    public function funcformmanageserveredit($oldserver, $oldip, $oldport): Closure
    {
        return function (Player $player, $data) use ($oldserver, $oldip, $oldport) {
            if (!isset($data)) {
                $this->sendfirstform($player);
                return;
            }
            $server = $data["server"];
            $ip = $data["ip"];
            $port = $data["port"];
            if ($oldserver === $server && $oldip === $ip && $oldport === $port) {
                $form = new SimpleForm($this->funcformmanage());
                $form->setTitle("§6No change");
                $form->setContent("§eNo changes detected");
                $form->sendToPlayer($player);
                return;
            }

            self::$database->executeChangeRaw(
                Utils::parsemysql("UPDATE `players` SET server = '{args1}', ip = '{args2}', port = '{args3}'
                WHERE player = '{args4}' AND server = '$oldserver'",
                    [$server, $ip, $port, $player->getName()]));
            unset($this->current[$player->getName()][$oldserver]);
            $this->current[$player->getName()][$server] = ["ip" => $ip, "port" => $port];
            $this->successfullform($player,
                function (Player $player, $data) {
                    $this->sendfirstform($player);
                });
        };
    }

    public function funcformmanageadd(): Closure
    {
        return function (Player $player, $data) {
            if (!isset($data)) {
                $this->sendfirstform($player);
                return;
            }
            $pname = $player->getName();
            $server = $data["server"];
            $ip = $data["ip"];
            $port = $data["port"];
            $form = new SimpleForm(function (Player $player, $data) {$this->openAddForm($player);});
            if (is_int($port) || $port > 66000 || $port < 1) {
                $form->setTitle("§6Bad port");
                $form->setContent("§cThe port must be a integer valid between 0-65535");
                $form->sendToPlayer($player);
                return;
            }else if(count($this->current[$pname]) > 100){
                $form->setTitle("§6Atteint limite serveur");
                $form->setContent("§cTu te fou un peu de ma gueule, ça fait beaucoup de serveur là");
                $form->addButton("Back");
                return;
            }else if(isset($this->current[$pname][$server])){
                $form->setTitle("§6Server already exist");
                $form->setContent("§cThis name of server is already used. Please use another one");
                $form->addButton("Back");
                return;
            }else if($server === "" || $ip === "" || $port === ""){
                $form->setTitle("§6Empty values");
                $form->setContent("§cPlease complete all the boxes.");
                $form->addButton("Back");
                return;
            }else if(strlen($server) >= 50|| strlen($ip) >= 50){
                $form->setTitle("§6Too large value");
                $form->setContent("§cIP and server must not be exceed 50 character.");
                $form->addButton("Back");
                return;
            }

            $status = json_decode(file_get_contents("https://api.mcsrvstat.us/bedrock/2/$ip:$port", false,
                stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]])));

            if (!$status->debug->query === true or !$status->online === true) {
                $form = new SimpleForm(function (Player $player, $data) use ($ip, $port, $server, $pname) {
                    if ($data === "yes")
                        $this->addServer($pname, $server, $ip, $port, $player);
                    else $this->openAddForm($player);
                });
                $form->setTitle("§6Server down");
                $form->setContent("§cThe server seems to be down, are you sure to continue ? \n§e$ip:$port (".$status->ip.":".$status->port.")");
                $form->addButton("§eYes", -1, "", "yes");
                $form->addButton("§eNo, cancel", -1, "", "no");
                $form->sendToPlayer($player);
                return;
            }
            $this->addServer($pname, $server, $ip, $port, $player);
        };
    }

    public function addServer($pname, $server, $ip, $port, $player)
    {
        self::$database->executeChangeRaw(
            Utils::parsemysql("INSERT INTO `players` VALUES ('{args1}', '{args2}', '{args3}', '{args4}')",
                [$pname, $server, $ip, $port]));
        $this->current[$pname][$server] = ["ip" => $ip, "port" => $port];
        $this->successfullform($player,
            function (Player $player, $data) {
                $this->sendfirstform($player);
            });
    }

    public function successfullform(Player $player, Closure $back)
    {
        $form = new SimpleForm($back);
        $form->setTitle("§6Successful");
        $form->setContent("§eAction executed successfully");
        $form->addButton("Complete");
        $form->sendToPlayer($player);
    }
}