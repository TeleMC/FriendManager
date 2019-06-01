<?php
namespace FriendManager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener {
    private $plugin;

    public function __construct(FriendManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $ev) {
        $player = $ev->getPlayer();
        if (!$this->plugin->isRegistered($player->getName()))
            $this->plugin->register($player->getName());
    }
}
