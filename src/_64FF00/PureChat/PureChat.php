<?php

namespace _64FF00\PureChat;

use _64FF00\PurePerms\PPGroup;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\Player;

use Prim\PrimCore\Main;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

class PureChat extends PluginBase
{
    const MAIN_PREFIX = "\x5b\x50\x75\x72\x65\x43\x68\x61\x74\x3a\x36\x34\x46\x46\x30\x30\x5d";

    /** @var Config $config */
    private $config;

    /** @var \_64FF00\PurePerms\PurePerms $purePerms */
    private $purePerms;
	
	private $kdr;

    public function onLoad(){
        $this->saveDefaultConfig();

        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        if(!$this->config->get("version")){
            $version = $this->getDescription()->getVersion();

            $this->config->set("version", $version);

            $this->fixOldConfig();
        }

        $this->purePerms = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
    }
    
    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents(new PCListener($this), $this);
    }

    /**
     * @param CommandSender $sender
     * @param Command $cmd
     * @param string $label
     * @param array $args
     */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
        switch(strtolower($cmd->getName())){
            case "setformat":
                if(count($args) < 3){
                    $sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " Usage: /setformat <group> <world> <format>");
                    return true;
                }

                $group = $this->purePerms->getGroup($args[0]);

                if($group === null){
                    $sender->sendMessage(TF::RED . self::MAIN_PREFIX . " Group " . $args[0] . "does NOT exist.");

                    return true;
                }

                $levelName = null;

                if($args[1] !== "null" and $args[1] !== "global"){
                    /** @var \pocketmine\level\Level $level */
                    $level = $this->getServer()->getLevelByName($args[1]);

                    if ($level === null){
                        $sender->sendMessage(TF::RED . self::MAIN_PREFIX . " Invalid World Name!");

                        return true;
                    }

                    $levelName = $level->getName();
                }

                $chatFormat = implode(" ", array_slice($args, 2));

                $this->setOriginalChatFormat($group, $chatFormat, $levelName);

                $sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " You set the chat format of the group to " . $chatFormat . ".");

                break;

            case "setnametag":

                if(count($args) < 3){
                    $sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " Usage: /setnametag <group> <world> <format>");

                    return true;
                }

                $group = $this->purePerms->getGroup($args[0]);

                if($group === null){
                    $sender->sendMessage(TF::RED . self::MAIN_PREFIX . " Group " . $args[0] . "does NOT exist.");

                    return true;
                }

                $levelName = null;

                if($args[1] !== "null" and $args[1] !== "global"){
                    /** @var \pocketmine\level\Level $level */
                    $level = $this->getServer()->getLevelByName($args[1]);

                    if ($level === null){
                        $sender->sendMessage(TF::RED . self::MAIN_PREFIX . " Invalid World Name!");

                        return true;
                    }

                    $levelName = $level->getName();
                }

                $nameTag = implode(" ", array_slice($args, 2));

                $this->setOriginalNametag($group, $nameTag, $levelName);

                $sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " You set the nametag of the group to " . $nameTag . ".");

                break;


            case "setprefix":
			if(!isset($args[0])){
                $sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " Usage: /setprefix <player> <prefix>");
                return true;
                }
				$playerName = $args[0];
				$player = $this->getServer()->getPlayer($playerName);
				if($player === null) {
					$sender->sendMessage(TF::RED . "$playerName is offline!");
					return true;
				}
				$pplayer = $player->getName();
				array_shift($args);
				$prefix = str_replace("{BLANK}", ' ', implode(' ', $args));
				$this->setPrefix("$prefix ", $player, null);
				$sender->sendMessage(TF::GREEN . PureChat::MAIN_PREFIX . " You set " . TF::YELLOW . "$pplayer" . TF::GREEN . "'s prefix to " . TF::YELLOW . $prefix . TF::GREEN . "!");
                break;
			case "clearprefix":
				if(!isset($args[0])){
					$sender->sendMessage(TF::GREEN . self::MAIN_PREFIX . " Usage: /clearprefix <player>");
					return true;
				}
				$playerName = $args[0];
				$player = $this->getServer()->getPlayer($playerName);
				if($player === null) {
					$sender->sendMessage(TF::RED . "$playerName is offline!");
					return true;
				}
				$pplayer = $player->getName();
				array_shift($args);
				$this->purePerms->getUserDataMgr()->setNode($player, "prefix", null);
				$sender->sendMessage(TF::GREEN . PureChat::MAIN_PREFIX . " You reset " . TF::YELLOW . "$pplayer" . TF::GREEN . "'s tag.");
				break;
        }

        return true;
    }

    private function fixOldConfig(){
        $tempData = $this->config->getAll();

        $version = $this->getDescription()->getVersion();

        $tempData["version"] = $version;

        if(isset($tempData["enable-multiworld-support"])){
            $tempData["enable-multiworld-chat"] = $tempData["enable-multiworld-support"];

            unset($tempData["enable-multiworld-support"]);
        }

        if(isset($tempData["groups"])){
            foreach($tempData["groups"] as $groupName => $tempGroupData){
                if(isset($tempGroupData["default-chat"])){
                    $tempGroupData["chat"] = $this->fixOldData($tempGroupData["default-chat"]);

                    unset($tempGroupData["default-chat"]);
                }

                if(isset($tempGroupData["default-nametag"])){
                    $tempGroupData["nametag"] = $this->fixOldData($tempGroupData["default-nametag"]);

                    unset($tempGroupData["default-nametag"]);
                }

                if(isset($tempGroupData["worlds"])){
                    foreach($tempGroupData["worlds"] as $worldName => $worldData){
                        if(isset($worldData["default-chat"])){
                            $worldData["chat"] = $this->fixOldData($worldData["default-chat"]);

                            unset($worldData["default-chat"]);
                        }

                        if(isset($worldData["default-nametag"]))
                        {
                            $worldData["nametag"] = $this->fixOldData($worldData["default-nametag"]);

                            unset($worldData["default-nametag"]);
                        }

                        $tempGroupData["worlds"][$worldName] = $worldData;
                    }
                }

                $tempData["groups"][$groupName] = $tempGroupData;
            }
        }

        $this->config->setAll($tempData);
        $this->config->save();

        $this->config->reload();

        $this->getLogger()->notice("Upgraded PureChat config.yml to the latest version");
    }

    /**
     * @param $string
     * @return mixed
     */
    private function fixOldData($string)
    {
        $string = str_replace("{COLOR_BLACK}", "&0", $string);
        $string = str_replace("{COLOR_DARK_BLUE}", "&1", $string);
        $string = str_replace("{COLOR_DARK_GREEN}", "&2", $string);
        $string = str_replace("{COLOR_DARK_AQUA}", "&3", $string);
        $string = str_replace("{COLOR_DARK_RED}", "&4", $string);
        $string = str_replace("{COLOR_DARK_PURPLE}", "&5", $string);
        $string = str_replace("{COLOR_GOLD}", "&6", $string);
        $string = str_replace("{COLOR_GRAY}", "&7", $string);
        $string = str_replace("{COLOR_DARK_GRAY}", "&8", $string);
        $string = str_replace("{COLOR_BLUE}", "&9", $string);
        $string = str_replace("{COLOR_GREEN}", "&a", $string);
        $string = str_replace("{COLOR_AQUA}", "&b", $string);
        $string = str_replace("{COLOR_RED}", "&c", $string);
        $string = str_replace("{COLOR_LIGHT_PURPLE}", "&d", $string);
        $string = str_replace("{COLOR_YELLOW}", "&e", $string);
        $string = str_replace("{COLOR_WHITE}", "&f", $string);

        $string = str_replace("{FORMAT_OBFUSCATED}", "&k", $string);
        $string = str_replace("{FORMAT_BOLD}", "&l", $string);
        $string = str_replace("{FORMAT_STRIKETHROUGH}", "&m", $string);
        $string = str_replace("{FORMAT_UNDERLINE}", "&n", $string);
        $string = str_replace("{FORMAT_ITALIC}", "&o", $string);
        $string = str_replace("{FORMAT_RESET}", "&r", $string);

        $string = str_replace("{world_name}", "{world}", $string);
        $string = str_replace("{user_name}", "{display_name}", $string);
        $string = str_replace("{message}", "{msg}", $string);

        return $string;
    }

    /**
     * @param $string
     * @return mixed
     */
    public function applyColors($string){
        $string = str_replace("&0", TF::BLACK, $string);
        $string = str_replace("&1", TF::DARK_BLUE, $string);
        $string = str_replace("&2", TF::DARK_GREEN, $string);
        $string = str_replace("&3", TF::DARK_AQUA, $string);
        $string = str_replace("&4", TF::DARK_RED, $string);
        $string = str_replace("&5", TF::DARK_PURPLE, $string);
        $string = str_replace("&6", TF::GOLD, $string);
        $string = str_replace("&7", TF::GRAY, $string);
        $string = str_replace("&8", TF::DARK_GRAY, $string);
        $string = str_replace("&9", TF::BLUE, $string);
        $string = str_replace("&a", TF::GREEN, $string);
        $string = str_replace("&b", TF::AQUA, $string);
        $string = str_replace("&c", TF::RED, $string);
        $string = str_replace("&d", TF::LIGHT_PURPLE, $string);
        $string = str_replace("&e", TF::YELLOW, $string);
        $string = str_replace("&f", TF::WHITE, $string);
        $string = str_replace("&k", TF::OBFUSCATED, $string);
        $string = str_replace("&l", TF::BOLD, $string);
        $string = str_replace("&m", TF::STRIKETHROUGH, $string);
        $string = str_replace("&n", TF::UNDERLINE, $string);
        $string = str_replace("&o", TF::ITALIC, $string);
        $string = str_replace("&r", TF::RESET, $string);

        return $string;
    }

    /**
     * @param $string
     * @param Player $player
     * @param $message
     * @param null $levelName
     * @return mixed
     */
    public function applyPCTags($string, Player $player, $message, $levelName){
        $string = str_replace("{display_name}", $player->getDisplayName(), $string);

        if($message === null)
            $message = "";

        if($player->hasPermission("pchat.coloredMessages")){
            $string = str_replace("{msg}", $this->applyColors($message), $string);
        }
        else
        {
            $string = str_replace("{msg}", $this->stripColors($message), $string);
        }

		$this->kdr = Main::getInstance();
		
        $string = str_replace("{kills}", $this->kdr->getProvider()->getPlayerKillPoints($player), $string);
	$string = str_replace(">", "»", $string);
        $string = str_replace("{world}", ($levelName === null ? "" : $levelName), $string);

        $string = str_replace("{prefix}", $this->getPrefix($player, $levelName), $string);
        $string = str_replace("{suffix}", $this->getSuffix($player, $levelName), $string);

        return $string;
    }

    /**
     * @param Player $player
     * @param $message
     * @param null $levelName
     * @return mixed
     */
    public function getChatFormat(Player $player, $message, $levelName = null){
        $originalChatFormat = $this->getOriginalChatFormat($player, $levelName);

        $chatFormat = $this->applyColors($originalChatFormat);
        $chatFormat = $this->applyPCTags($chatFormat, $player, $message, $levelName);

        return $chatFormat;
    }

    /**
     * @param Player $player
     * @param null $levelName
     * @return mixed
     */
    public function getNametag(Player $player, $levelName = null){
        $originalNametag = $this->getOriginalNametag($player, $levelName);

        $nameTag = $this->applyColors($originalNametag);
        $nameTag = $this->applyPCTags($nameTag, $player, null, $levelName);

        return $nameTag;
    }

    /**
     * @param Player $player
     * @param null $levelName
     * @return mixed
     */
    public function getOriginalChatFormat(Player $player, $levelName = null){
        /** @var \_64FF00\PurePerms\PPGroup $group */
        $group = $this->purePerms->getUserDataMgr()->getGroup($player, $levelName);

        if($levelName === null){
            if($this->config->getNested("groups." . $group->getName() . ".chat") === null){
                $this->getLogger()->critical("Invalid chat format found in config.yml (Group: " . $group->getName() . ") / Setting it to default value.");

                $this->config->setNested("groups." . $group->getName() . ".chat", "&8&l[" . $group->getName() . "]&f&r {display_name} &7> {msg}");

                $this->config->save();
                $this->config->reload();
            }

            return $this->config->getNested("groups." . $group->getName() . ".chat");
        }
        else{
            if($this->config->getNested("groups." . $group->getName() . "worlds.$levelName.chat") === null){
                $this->getLogger()->critical("Invalid chat format found in config.yml (Group: " . $group->getName() . ", WorldName = $levelName) / Setting it to default value.");

                $this->config->setNested("groups." . $group->getName() . "worlds.$levelName.chat", "&8&l[" . $group->getName() . "]&f&r {display_name} &7> {msg}");

                $this->config->save();
                $this->config->reload();
            }

            return $this->config->getNested("groups." . $group->getName() . "worlds.$levelName.chat");
        }
    }

    public function getOriginalNametag(Player $player, $levelName = null){
        /** @var \_64FF00\PurePerms\PPGroup $group */
        $group = $this->purePerms->getUserDataMgr()->getGroup($player, $levelName);

        if($levelName === null){
            if($this->config->getNested("groups." . $group->getName() . ".nametag") === null){
                $this->getLogger()->critical("Invalid nametag found in config.yml (Group: " . $group->getName() . ") / Setting it to default value.");

                $this->config->setNested("groups." . $group->getName() . ".nametag", "&8&l[" . $group->getName() . "]&f&r {display_name}");

                $this->config->save();
                $this->config->reload();
            }

            return $this->config->getNested("groups." . $group->getName() . ".nametag");
        }
        else{
            if($this->config->getNested("groups." . $group->getName() . "worlds.$levelName.nametag") === null){
                $this->getLogger()->critical("Invalid nametag found in config.yml (Group: " . $group->getName() . ", WorldName = $levelName) / Setting it to default value.");

                $this->config->setNested("groups." . $group->getName() . "worlds.$levelName.nametag", "&8&l[" . $group->getName() . "]&f&r {display_name}");

                $this->config->save();
                $this->config->reload();
            }

            return $this->config->getNested("groups." . $group->getName() . "worlds.$levelName.nametag");
        }
    }

    /**
     * @param Player $player
     * @param null $levelName
     * @return mixed|null|string
     */
    public function getPrefix(Player $player, $levelName = null){
        if($levelName === null){
            return $this->purePerms->getUserDataMgr()->getNode($player, "prefix");
        }
        else{
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $levelName);

            if(!isset($worldData["prefix"]) || $worldData["prefix"] === null)
                return "";

            return $worldData["prefix"];
        }
    }

    /**
     * @param Player $player
     * @param null $levelName
     * @return mixed|null|string
     */
    public function getSuffix(Player $player, $levelName = null){
        if($levelName === null){
            return $this->purePerms->getUserDataMgr()->getNode($player, "suffix");
        }
        else{
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $levelName);

            if(!isset($worldData["suffix"]) || $worldData["suffix"] === null)
                return "";

            return $worldData["suffix"];
        }
    }

    /**
     * @param PPGroup $group
     * @param $chatFormat
     * @param null $levelName
     * @return bool
     */
    public function setOriginalChatFormat(PPGroup $group, $chatFormat, $levelName = null){
        if($levelName === null){
            $this->config->setNested("groups." . $group->getName() . ".chat", $chatFormat);
        }
        else{
            $this->config->setNested("groups." . $group->getName() . "worlds.$levelName.chat", $chatFormat);
        }

        $this->config->save();
        $this->config->reload();
        return true;
    }

    /**
     * @param PPGroup $group
     * @param $nameTag
     * @param null $levelName
     * @return bool
     */
    public function setOriginalNametag(PPGroup $group, $nameTag, $levelName = null){
        if($levelName === null){
            $this->config->setNested("groups." . $group->getName() . ".nametag", $nameTag);
        }
        else{
            $this->config->setNested("groups." . $group->getName() . "worlds.$levelName.nametag", $nameTag);
        }

        $this->config->save();
        $this->config->reload();
        return true;
    }

    /**
     * @param $prefix
     * @param Player $player
     * @param null $levelName
     * @return bool
     */
    public function setPrefix($prefix, Player $player, $levelName = null)
    {
        if($levelName === null){
            $this->purePerms->getUserDataMgr()->setNode($player, "prefix", $prefix);
        }
        else{
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $levelName);

            $worldData["prefix"] = $prefix;

            $this->purePerms->getUserDataMgr()->setWorldData($player, $levelName, $worldData);
        }
        return true;
    }

    /**
     * @param $suffix
     * @param Player $player
     * @param null $levelName
     * @return bool
     */
    public function setSuffix($suffix, Player $player, $levelName = null){
        if($levelName === null){
            $this->purePerms->getUserDataMgr()->setNode($player, "suffix", $suffix);
        }
        else{
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $levelName);

            $worldData["suffix"] = $suffix;

            $this->purePerms->getUserDataMgr()->setWorldData($player, $levelName, $worldData);
        }

        return true;
    }

    /**
     * @param $string
     * @return mixed
     */
    public function stripColors($string){
        $string = str_replace(TF::BLACK, '', $string);
        $string = str_replace(TF::DARK_BLUE, '', $string);
        $string = str_replace(TF::DARK_GREEN, '', $string);
        $string = str_replace(TF::DARK_AQUA, '', $string);
        $string = str_replace(TF::DARK_RED, '', $string);
        $string = str_replace(TF::DARK_PURPLE, '', $string);
        $string = str_replace(TF::GOLD, '', $string);
        $string = str_replace(TF::GRAY, '', $string);
        $string = str_replace(TF::DARK_GRAY, '', $string);
        $string = str_replace(TF::BLUE, '', $string);
        $string = str_replace(TF::GREEN, '', $string);
        $string = str_replace(TF::AQUA, '', $string);
        $string = str_replace(TF::RED, '', $string);
        $string = str_replace(TF::LIGHT_PURPLE, '', $string);
        $string = str_replace(TF::YELLOW, '', $string);
        $string = str_replace(TF::WHITE, '', $string);
        $string = str_replace(TF::OBFUSCATED, '', $string);
        $string = str_replace(TF::BOLD, '', $string);
        $string = str_replace(TF::STRIKETHROUGH, '', $string);
        $string = str_replace(TF::UNDERLINE, '', $string);
        $string = str_replace(TF::ITALIC, '', $string);
        $string = str_replace(TF::RESET, '', $string);
        return $string;
    }
}
