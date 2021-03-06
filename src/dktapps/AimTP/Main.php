<?php

declare(strict_types=1);

namespace dktapps\AimTP;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{
	private const AIMSTICK_TAG = 'compasstp';

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case 'compasstp':
				if(!($sender instanceof Player)){
					if(!isset($args[1]) or ($player = $sender->getServer()->getPlayer($args[1])) === null){
						$sender->sendMessage(TextFormat::RED . "You must specify a player from the console");
						return true;
					}
				}else{
					$player = $sender;
				}
				$stick = ItemFactory::get(Item::COMPASS);
				$stick->setCustomName("Compass Teleport");
				$stick->setNamedTagEntry(new ByteTag(self::AIMSTICK_TAG, 1));
				$player->getInventory()->addItem($stick);
				Command::broadcastCommandMessage($sender, "Gave " . $sender->getName() . " a teleporter compass");
				return true;
			default:
				return false;
		}
	}

	public function onBreakBlock(BlockBreakEvent $event){
		//prevent PE breaking blocks by accident
		if($event->getItem()->getNamedTagEntry(self::AIMSTICK_TAG) !== null){
			$event->setCancelled();
		}
	}

	public function onItemUse(PlayerInteractEvent $event){
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR and $event->getItem()->getNamedTagEntry(self::AIMSTICK_TAG) !== null){
			$player = $event->getPlayer();
			if(!$player->hasPermission('compass.teleport.use')){
				$player->sendMessage(TextFormat::RED . 'You don\'t have permission to use this item');
				return;
			}
			$start = $player->add(0, $player->getEyeHeight(), 0);
			$end = $start->add($player->getDirectionVector()->multiply($player->getViewDistance() * 16));
			$level = $player->level;

			foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
				if($vector3->y >= Level::Y_MAX or $vector3->y <= 0){
					return;
				}

				if(!$level->isChunkLoaded($vector3->x >> 4, $vector3->z >> 4) or !$level->getChunk($vector3->x >> 4, $vector3->z >> 4)->isGenerated()){
					return;
				}

				if(($result = $level->getBlockAt($vector3->x, $vector3->y, $vector3->z)->calculateIntercept($start, $end)) !== null){
					$target = $result->hitVector;
					$player->teleport($target);
					return;
				}
			}
		}
	}
}
