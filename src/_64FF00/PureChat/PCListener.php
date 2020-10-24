<?php

namespace _64FF00\PureChat;

use _64FF00\PurePerms\event\PPGroupChangedEvent;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\Player;

class PCListener implements Listener{

    private $plugin;

    /**
 * @param PureChat $plugin
 */
    public function __construct(PureChat $plugin){
        $this->plugin = $plugin;
    }

    public function onGroupChanged(PPGroupChangedEvent $event){
        /** @var \pocketmine\IPlayer $player */
        $player = $event->getPlayer();
		
		if($player instanceof Player){
			$levelName = false ? $player->getLevel()->getName() : null;

			$nameTag = $this->plugin->getNametag($player, $levelName);

			$player->setNameTag($nameTag);
		}
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGH
     */
    public function onPlayerJoin(PlayerJoinEvent $event){
        /** @var \pocketmine\Player $player */
        $player = $event->getPlayer();
        $levelName = false ? $player->getLevel()->getName() : null;
        $nameTag = $this->plugin->getNametag($player, $levelName);
        $player->setNameTag($nameTag);
    }

    /**
     * @param PlayerChatEvent $event
     * @priority HIGH
     */
    public function onPlayerChat(PlayerChatEvent $event){
		if($event->isCancelled()) return;
		$player = $event->getPlayer();
        $message = $event->getMessage();
        $levelName = null;
        $chatFormat = $this->plugin->getChatFormat($player, $message, $levelName);
        $event->setFormat($chatFormat);
    }
}
