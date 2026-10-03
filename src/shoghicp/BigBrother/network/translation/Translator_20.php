<?php

/*
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014 shoghicp <https://github.com/shoghicp/BigBrother>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace shoghicp\BigBrother\network\translation;

use pocketmine\item\Item;
use pocketmine\network\protocol\ContainerClosePacket;
use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\DropItemPacket;
use pocketmine\network\protocol\Info;
use pocketmine\network\protocol\InteractPacket;
use pocketmine\network\protocol\MobEquipmentPacket;
use pocketmine\network\protocol\TextPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\PlayerActionPacket;
use pocketmine\network\protocol\RemoveBlockPacket;
use pocketmine\network\protocol\RespawnPacket;
use pocketmine\network\protocol\UseItemPacket;
use pocketmine\utils\TextFormat;
use shoghicp\BigBrother\DesktopPlayer;
use shoghicp\BigBrother\network\Packet;
use shoghicp\BigBrother\network\protocol\BlockChangePacket;
use shoghicp\BigBrother\network\protocol\ChangeGameStatePacket;
use shoghicp\BigBrother\network\protocol\DestroyEntitiesPacket;
use shoghicp\BigBrother\network\protocol\EntityHeadLookPacket;
use shoghicp\BigBrother\network\protocol\EntityMetadataPacket;
use shoghicp\BigBrother\network\protocol\EntityTeleportPacket;
use shoghicp\BigBrother\network\protocol\EntityVelocityPacket;
use shoghicp\BigBrother\network\protocol\JoinGamePacket;
use shoghicp\BigBrother\network\protocol\OpenWindowPacket;
use shoghicp\BigBrother\network\protocol\PlayerAbilitiesPacket;
use shoghicp\BigBrother\network\protocol\PositionAndLookPacket;
use shoghicp\BigBrother\network\protocol\SetSlotPacket;
use shoghicp\BigBrother\network\protocol\SpawnObjectPacket;
use shoghicp\BigBrother\network\protocol\SpawnPlayerPacket;
use shoghicp\BigBrother\network\protocol\SpawnPositionPacket;
use shoghicp\BigBrother\network\protocol\STCChatPacket;
use shoghicp\BigBrother\network\protocol\STCCloseWindowPacket;
use shoghicp\BigBrother\network\protocol\TimeUpdatePacket;
use shoghicp\BigBrother\network\protocol\UpdateHealthPacket;
use shoghicp\BigBrother\network\protocol\WindowItemsPacket;
use shoghicp\BigBrother\utils\Binary;

class Translator_20 implements Translator{



	public function interfaceToServer(DesktopPlayer $player, Packet $packet){
		switch($packet->pid()){
			// TODO: move to Info

			case 0x01: //CTSChatPacket
				$pk = new TextPacket();
				$pk->type = TextPacket::TYPE_CHAT;
				$pk->source = "";
				$pk->message = $packet->message;
				return $pk;

			case 0x02: //UseEntityPacket
				//1.8 mouse: 0=interact, 1=attack, 2=interact at -> MCPE:
				//RIGHT_CLICK=1, LEFT_CLICK=2
				$pk = new InteractPacket();
				$pk->target = $packet->target;
				if($packet->mouse === 1){
					$pk->action = InteractPacket::ACTION_LEFT_CLICK;
				}else{ //0 or 2 both mean right-click
					$pk->action = InteractPacket::ACTION_RIGHT_CLICK;
				}
				return $pk;

			case 0x04: //PlayerPositionPacket
				$pk = new MovePlayerPacket();
				$pk->x = $packet->x;
				//1.8 position packets carry the FEET y; MCPE MovePlayerPacket
				//expects the EYE y (core subtracts eye height again).
				$pk->y = $packet->y + $player->getEyeHeight();
				$pk->z = $packet->z;
				$pk->yaw = $player->yaw;
				$pk->bodyYaw = $player->yaw;
				$pk->pitch = $player->pitch;
				return $pk;

			case 0x05: //PlayerLookPacket
				$pk = new MovePlayerPacket();
				$pk->x = $player->x;
				$pk->y = $player->y + $player->getEyeHeight();
				$pk->z = $player->z;
				$pk->yaw = $packet->yaw;
				$pk->bodyYaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				return $pk;

			case 0x06: //PlayerPositionAndLookPacket
				$pk = new MovePlayerPacket();
				$pk->x = $packet->x;
				$pk->y = $packet->y + $player->getEyeHeight();
				$pk->z = $packet->z;
				$pk->yaw = $packet->yaw;
				$pk->bodyYaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				return $pk;

			case 0x07: //PlayerDiggingPacket
				//1.8 digging statuses -> MCPE:
				//  0 = start digging  -> PlayerActionPacket ACTION_START_BREAK
				//  1 = cancel digging -> PlayerActionPacket ACTION_ABORT_BREAK
				//  2 = finished       -> RemoveBlockPacket (core's real dig path)
				//  3 = drop stack     -> DropItemPacket (whole stack)
				//  4 = drop item      -> DropItemPacket (single item)
				//Creative clients only send status 0 for instant breaks, so both
				//packets are emitted there.
				if($packet->status === 3 or $packet->status === 4){
					//Q / Ctrl+Q carry no item data on 1.8 - use the held item
					$inHand = $player->getInventory()->getItemInHand();
					if($inHand->getId() === 0){
						return null; //nothing to drop
					}
					$pk = new DropItemPacket();
					$pk->type = 0;
					if($packet->status === 4 and $inHand->getCount() > 1){
						$pk->item = clone $inHand;
						$pk->item->setCount(1);
					}else{
						$pk->item = $inHand;
					}
					return $pk;
				}
				$packets = [];
				if($packet->status === 0){
					$pa = new PlayerActionPacket();
					$pa->action = PlayerActionPacket::ACTION_START_BREAK;
					$pa->eid = 0;
					$pa->x = $packet->x;
					$pa->y = $packet->y;
					$pa->z = $packet->z;
					$pa->face = $packet->face;
					$packets[] = $pa;
					if($player->getGamemode() !== 1){
						return $pa; //survival: block only breaks on status 2
					}
				}elseif($packet->status === 1){
					$pk = new PlayerActionPacket();
					$pk->action = PlayerActionPacket::ACTION_ABORT_BREAK;
					$pk->eid = 0;
					$pk->x = $packet->x;
					$pk->y = $packet->y;
					$pk->z = $packet->z;
					$pk->face = $packet->face;
					return $pk;
				}

				if($packet->status === 2 or ($player->getGamemode() === 1 and $packet->status === 0)){
					$pk = new RemoveBlockPacket();
					$pk->eid = 0;
					$pk->x = $packet->x;
					$pk->y = $packet->y;
					$pk->z = $packet->z;
					$packets[] = $pk;
				}
				return count($packets) > 0 ? $packets : null;

			case 0x08; //PlayerBlockPlacementPacket
				$pk = new UseItemPacket();
				//1.8 sends x=y=z=-1 / face=255 when using an item on air; MCPE
				//protocol 70 expects face=0xff with a normalised aim vector.
				if($packet->direction === 0xff or $packet->direction === 255){
					$pk->x = 0;
					$pk->y = 0;
					$pk->z = 0;
					$pk->face = 0xff;
				}else{
					$pk->x = $packet->x;
					$pk->y = $packet->y;
					$pk->z = $packet->z;
					$pk->face = $packet->direction;
				}
				$pk->item = $packet->heldItem->getId();
				$pk->meta = $packet->heldItem->getDamage();
				$pk->eid = 0;
				$pk->fx = $packet->cursorX / 16;
				$pk->fy = $packet->cursorY / 16;
				$pk->fz = $packet->cursorZ / 16;
				$pk->posX = $pk->posY = $pk->posZ = 0;
				//Player::handleDataPacket() calls decodeAdditional() on this packet,
				//which re-reads slot+item from the buffer - so pre-fill it in the
				//MCPE slot format (int slot, then short id / byte count / short
				//damage / lshort nbtLen) to keep the decoded values consistent.
				$pk->slot = -1;
				$buffer = "";
				$buffer .= \pocketmine\utils\Binary::writeInt(-1);
				if($packet->heldItem->getId() === 0){
					$buffer .= \pocketmine\utils\Binary::writeShort(0);
				}else{
					$buffer .= \pocketmine\utils\Binary::writeShort($packet->heldItem->getId());
					$buffer .= chr($packet->heldItem->getCount());
					$buffer .= \pocketmine\utils\Binary::writeShort($packet->heldItem->getDamage());
					$buffer .= \pocketmine\utils\Binary::writeLShort(0);
				}
				$pk->setBuffer($buffer);
				return $pk;

			case 0x0d: //CTSCloseWindowPacket
				$pk = new ContainerClosePacket();
				$pk->windowid = $packet->windowID;
				return $pk;

			case 0x09: //HeldItemChangePacket - 1.8 hotbar slot switch
				//The core validates the carried item against inventory contents,
				//so hand it the actual item present at that hotbar slot.
				$pk = new MobEquipmentPacket();
				$pk->eid = 0;
				$pk->item = $player->getInventory()->getItem($packet->slot);
				$pk->slot = $packet->slot + 9; //core subtracts 9 to get the real slot
				$pk->selectedSlot = $packet->slot;
				return $pk;

			case 0x16: //ClientStatusPacket
				if($packet->actionID === 0){ //perform respawn
					//1.8 clients request respawn after clicking the button on the
					//death screen; the core never handles an inbound RespawnPacket,
					//so the DesktopPlayer handles it directly.
					$player->bigBrother_respawn();
				}
				return null;

			default:
				return null;
		}
	}

	public function serverToInterface(DesktopPlayer $player, DataPacket $packet){
		switch($packet->pid()){

			case Info::UPDATE_BLOCK_PACKET:
				//MPMPESCore (protocol 70) batches block updates: each record is
				//[x, z, y, blockId, blockData, flags]
				$packets = [];
				foreach($packet->records as $r){
					$pk = new BlockChangePacket();
					$pk->x = $r[0];
					$pk->y = $r[2];
					$pk->z = $r[1];
					$pk->blockId = $r[3];
					$pk->blockMeta = $r[4];
					$packets[] = $pk;
				}
				return count($packets) > 0 ? $packets : null;

			case Info::START_GAME_PACKET:
				$packets = [];

				$pk = new JoinGamePacket();
				$pk->eid = $packet->eid;
				$pk->gamemode = $player->getGamemode();
				$pk->dimension = 0;
				$pk->difficulty = $player->getServer()->getDifficulty();
				$pk->maxPlayers = $player->getServer()->getMaxPlayers();
				$pk->levelType = "default";
				$packets[] = $pk;

				$pk = new PlayerAbilitiesPacket();
				$pk->flyingSpeed = 0.05;
				$pk->walkingSpeed = 0.1;
				$pk->canFly = ($player->getGamemode() & 0x01) > 0;
				$pk->damageDisabled = ($player->getGamemode() & 0x01) > 0;
				$pk->isFlying = false;
				$pk->isCreative = ($player->getGamemode() & 0x01) > 0;
				if($player->spawned === true){
					$packets = [$pk];

					$pk = new ChangeGameStatePacket();
					$pk->reason = 3;
					$pk->value = $player->getGamemode();
					$packets[] = $pk;
					return $packets;
				}else{
					$packets[] = $pk;
				}

				$pk = new SpawnPositionPacket();
				$pk->spawnX = $packet->spawnX;
				$pk->spawnY = $packet->spawnY;
				$pk->spawnZ = $packet->spawnZ;
				$packets[] = $pk;

				$pk = new PositionAndLookPacket();
				$pk->x = $packet->x;
				$pk->y = $packet->y;
				$pk->z = $packet->z;
				$pk->yaw = $player->yaw;
				$pk->pitch = $player->pitch;
				$pk->onGround = $player->isOnGround();
				$packets[] = $pk;
				return $packets;

			case Info::SET_HEALTH_PACKET:
				$pk = new UpdateHealthPacket();
				$pk->health = $packet->health;
				$pk->food = 20;
				$pk->saturation = 5;
				return $pk;

			case Info::UPDATE_ATTRIBUTES_PACKET:
				//core sends health/hunger changes through the attribute system
				//(entityId 0 = the player themself); 1.8 clients need health 0
				//for the death screen, and hunger for the food bar.
				if($packet->entityId !== 0){
					return null;
				}
				$health = null;
				$food = null;
				foreach($packet->entries as $entry){
					if($entry->getName() === "generic.health"){
						$health = $entry->getValue();
					}elseif($entry->getName() === "player.hunger"){
						$food = $entry->getValue();
					}
				}
				if($health === null and $food === null){
					return null;
				}
				$pk = new UpdateHealthPacket();
				$pk->health = $health !== null ? $health : $player->getHealth();
				$pk->food = $food !== null ? $food : 20;
				$pk->saturation = 5;
				return $pk;

			case Info::SET_PLAYER_GAMETYPE_PACKET:
				$packets = [];
				$pk = new ChangeGameStatePacket();
				$pk->reason = 3; //change game mode
				$pk->value = $packet->gamemode;
				$packets[] = $pk;

				$pk = new PlayerAbilitiesPacket();
				$pk->flyingSpeed = 0.05;
				$pk->walkingSpeed = 0.1;
				$pk->canFly = ($packet->gamemode & 0x01) > 0;
				$pk->damageDisabled = ($packet->gamemode & 0x01) > 0;
				$pk->isFlying = false;
				$pk->isCreative = ($packet->gamemode & 0x01) > 0;
				$packets[] = $pk;
				return $packets;

			case Info::TEXT_PACKET:
				$pk = new STCChatPacket();

				$pk->message = TextFormat::toJSON($packet->message);
				return $pk;

			case Info::SET_TIME_PACKET:
				$pk = new TimeUpdatePacket();
				$pk->age = $packet->time;
				$pk->time = $packet->time; //TODO: calculate offset from MCPE
				return $pk;

			case Info::SET_SPAWN_POSITION_PACKET:
				$pk = new SpawnPositionPacket();
				$pk->spawnX = $packet->x;
				$pk->spawnY = $packet->y;
				$pk->spawnZ = $packet->z;
				return $pk;

			case Info::REMOVE_ENTITY_PACKET:
			case Info::REMOVE_PLAYER_PACKET:
				$pk = new DestroyEntitiesPacket();
				$pk->ids[] = $packet->eid;
				return $pk;

			case Info::MOVE_PLAYER_PACKET:
				if($packet->eid === 0){
					$pk = new PositionAndLookPacket();
					$pk->x = $packet->x;
					//MCPE sends the EYE y; 1.8 clients expect the FEET y
					$pk->y = $packet->y - $player->getEyeHeight();
					$pk->z = $packet->z;
					$pk->yaw = $packet->yaw;
					$pk->pitch = $packet->pitch;
					$pk->onGround = $player->isOnGround();
					return $pk;
				}else{
					$packets = [];
					$pk = new EntityTeleportPacket();
					$pk->eid = $packet->eid;
					$pk->x = $packet->x;
					$pk->y = $packet->y;
					$pk->z = $packet->z;
					$pk->yaw = $packet->yaw;
					$pk->pitch = $packet->pitch;
					$packets[] = $pk;

					$pk = new EntityHeadLookPacket();
					$pk->eid = $packet->eid;
					$pk->yaw = $packet->yaw;
					$packets[] = $pk;
					return $packets;
				}

			case Info::MOVE_ENTITY_PACKET:
				$packets = [];
				foreach($packet->entities as $d){
					$pk = new EntityTeleportPacket();
					$pk->eid = $d[0];
					$pk->x = $d[1];
					$pk->y = $d[2];
					$pk->z = $d[3];
					$pk->yaw = $d[4];
					$pk->pitch = $d[5];
					$packets[] = $pk;

					$pk = new EntityHeadLookPacket();
					$pk->eid = $d[0];
					$pk->yaw = $d[4];
					$packets[] = $pk;
				}
				return $packets;

			case Info::SET_ENTITY_MOTION_PACKET:
				$packets = [];
				foreach($packet->entities as $d){
					$pk = new EntityVelocityPacket();
					$pk->eid = $d[0];
					$pk->velocityX = $d[1];
					$pk->velocityY = $d[2];
					$pk->velocityZ = $d[3];
					$packets[] = $pk;
				}
				return $packets;

			case Info::CONTAINER_CLOSE_PACKET:
				$pk = new STCCloseWindowPacket();
				$pk->windowID = $packet->windowid;
				return $pk;

			case Info::CONTAINER_SET_SLOT_PACKET:
				$pk = new SetSlotPacket();
				$pk->windowID = $packet->windowid;
				if($pk->windowID === 0){
					//MCPE player inventory: 0-8 hotbar, 9-35 main inventory.
					//PC 1.8 window 0: 9-35 main inventory, 36-44 hotbar.
					if($packet->slot < 9){
						$pk->slot = $packet->slot + 36;
					}else{
						$pk->slot = $packet->slot;
					}
				}elseif($pk->windowID === 0x78){ //SPECIAL_ARMOR
					$pk->windowID = 0;
					$pk->slot = $packet->slot + 5;
				}else{
					$pk->slot = $packet->slot;
				}
				$pk->item = $packet->item;
				return $pk;

			case Info::CONTAINER_SET_CONTENT_PACKET:
				if($packet->windowid === 0x79){ //SPECIAL_CREATIVE
					//MCPE creative menu contents - PC clients must not see this
					return null;
				}
				$pk = new WindowItemsPacket();
				$pk->windowID = $packet->windowid;
				if($pk->windowID === 0 or $pk->windowID === 0x78){
					$pk->windowID = 0;
					$inv = $player->getInventory();
					//PC 1.8 window 0: 0 craft result, 1-4 craft grid, 5-8 armor,
					//9-35 main inventory, 36-44 hotbar
					for($i = 0; $i < 5; ++$i){
						$pk->items[] = Item::get(Item::AIR, 0, 0);
					}
					$pk->items[] = $inv->getHelmet();
					$pk->items[] = $inv->getChestplate();
					$pk->items[] = $inv->getLeggings();
					$pk->items[] = $inv->getBoots();
					//main inventory: MCPE 9-35 -> PC 9-35
					for($i = 9; $i < 36; ++$i){
						$pk->items[] = $inv->getItem($i);
					}
					//hotbar: MCPE 0-8 -> PC 36-44
					for($i = 0; $i < 9; ++$i){
						$pk->items[] = $inv->getItem($i);
					}
				}else{
					$pk->items = $packet->slots;
				}

				return $pk;

			case Info::ADD_ITEM_ENTITY_PACKET:
				$packets = [];
				$pk = new SpawnObjectPacket();
				$pk->eid = $packet->eid;
				$pk->type = 2;
				$pk->x = $packet->x;
				$pk->y = $packet->y;
				$pk->z = $packet->z;
				//AddItemEntityPacket has no yaw/pitch fields
				$pk->yaw = 0;
				$pk->pitch = 0;
				$packets[] = $pk;

				$pk = new EntityMetadataPacket();
				$pk->eid = $packet->eid;
				$pk->metadata = $pk->metadata = [
					0 => ["type" => 0, "value" => 0],
					10 => ["type" => 5, "value" => $packet->item],
				];
				$packets[] = $pk;

				return $packets;


			case Info::ADD_PLAYER_PACKET:
				$packets = [];
				$pk = new SpawnPlayerPacket();
				$pk->name = $packet->username;
				$pk->eid = $packet->eid;
				$pk->uuid = Binary::UUIDtoString("00000000000030008000000000000000");
				$pk->x = $packet->x;
				$pk->z = $packet->y;
				$pk->y = $packet->z;
				$pk->yaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				$pk->item = 0;
				$pk->metadata = $packet->metadata;
				$packets[] = $pk;

				$pk = new EntityTeleportPacket();
				$pk->eid = $packet->eid;
				$pk->x = $packet->x;
				$pk->y = $packet->y;
				$pk->z = $packet->z;
				$pk->yaw = $packet->yaw;
				$pk->pitch = $packet->pitch;
				$packets[] = $pk;
				return $packets;

			default:
				return null;
		}
	}
}