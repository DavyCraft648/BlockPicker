<?php

namespace DavyCraft648\BlockPicker;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBlockPickEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemFactory;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class BlockPicker extends PluginBase implements Listener {

    private static $prefix = "";
    private static $pickMode = false;
    private static $enabledPlayers = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        self::$prefix = (string)$this->getConfig()->get("prefix", "");
        self::$pickMode = (bool)$this->getConfig()->get("betterBlockPick", false);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($sender instanceof Player) $this->togglePlayer($sender);
        return true;
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority LOW
     */
    public function handleInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $blockClicked = $event->getBlock();
        if (isset(self::$enabledPlayers[$player->getName()]) &&
            $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            if (!self::$pickMode && $player->isCreative()) {
                $resultItem = $this->getConfig()->get("dontRemoveData", false) ?
                    ItemFactory::get($blockClicked->getItemId(), $blockClicked->getDamage()) : $blockClicked->getPickedItem();
                $ev = new PlayerBlockPickEvent($player, $blockClicked, $resultItem);
                $ev->call();
                if (!$ev->isCancelled()) {
                    $player->getInventory()->setItemInHand($resultItem = $ev->getResultItem());
                    if (($message = $this->getConfig()->get("pickedMessage", "")) !== "") {
                        $message = str_replace(["{ITEM_NAME}",
                            "{ITEM_ID}",
                            "{ITEM_META}",
                            "{BLOCK_NAME}",
                            "{BLOCK_ID}",
                            "{BLOCK_META}"], [$resultItem->getName(),
                            $resultItem->getId(),
                            $resultItem->getDamage(),
                            $blockClicked->getName(),
                            $blockClicked->getId(),
                            $blockClicked->getDamage()], $message);
                        $this->sendMessage($message, $player);
                    }
                }
            } elseif (self::$pickMode) {
                $bPlugin = $this->checkPlugin();
                if ($bPlugin != null) $bPlugin->handleBlockPick($player, $blockClicked);
            }
            $event->setCancelled();
            $this->togglePlayer($player);
        }
    }

    private function checkPlugin(): ?Plugin {
        return $this->getServer()->getPluginManager()->getPlugin("BetterPickBlock");
    }

    public function togglePlayer(Player $player): void {
        $name = $player->getName();
        if (!isset(self::$enabledPlayers[$name])) {
            self::$enabledPlayers[$name] = 1;
            if (($message = $this->getConfig()->get("tapToPickMessage", "")) !== "")
                $this->sendMessage($message, $player);
        } else unset(self::$enabledPlayers[$name]);
    }

    private function sendMessage(string $message, Player $player): void {
        $player->sendMessage(TextFormat::colorize((self::$prefix !== "" ? (self::$prefix . "&r ") : "") . $message));
    }
}