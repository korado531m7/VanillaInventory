<?php

/**
 * VanillaInventory
 *
 * Copyright (c) 2021 korado531m7
 *
 * This software is released under the MIT License.
 * http://opensource.org/licenses/mit-license.php
 */

namespace korado531m7\VanillaInventory\inventory;


use korado531m7\VanillaInventory\DataManager;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;
use pocketmine\Player;

abstract class FakeInventory extends ContainerInventory{

    /**
     * @return int
     */
    abstract public function getFirstVirtualSlot() : int;

    /**
     * @return int[]
     */
    abstract public function getVirtualSlots() : array;

    public function close(Player $who) : void{
        DataManager::resetTemporarilyData($who);

        parent::close($who);
    }

    public function listen(Player $who, InventoryTransactionPacket $packet) : void{
        $tmp = DataManager::getTemporarilyInventory($who);
        if($tmp instanceof $this){
            foreach($packet->actions as $action){
                if($action->sourceType === NetworkInventoryAction::SOURCE_CONTAINER){
                    $adjustedSlot = $action->inventorySlot - $this->getFirstVirtualSlot();
                    $ev = new InventoryTransactionEvent(new InventoryTransaction($who, [new SlotChangeAction($tmp, $adjustedSlot, $action->oldItem, $action->newItem)]));
                    $ev->call();

                    if($action->windowId === ContainerIds::UI && in_array($action->inventorySlot, $this->getVirtualSlots(), true)){
                        $tmp->setItem($adjustedSlot, $ev->isCancelled() ? $action->oldItem : $action->newItem);
                    }else{
                        $who->getWindow($action->windowId)->setItem($action->inventorySlot, $ev->isCancelled() ? $action->oldItem : $action->newItem);
                    }
                }
            }
        }
    }

    public static function dealXp(Player $player, ActorEventPacket $packet) : void{
        if($packet->event === ActorEventPacket::PLAYER_ADD_XP_LEVELS && DataManager::equalsTemporarilyInventory($player, static::class)){
            $player->addXpLevels($packet->data);
        }
    }

}