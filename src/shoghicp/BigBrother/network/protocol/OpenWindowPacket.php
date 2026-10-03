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

namespace shoghicp\BigBrother\network\protocol;

use shoghicp\BigBrother\network\Packet;

class OpenWindowPacket extends Packet{

	public $windowID;
	public $inventoryType;
	public $windowTitle;
	public $slots;
	public $useTitle = false;
	public $entityId;

	public function pid(){
		return 0x2d;
	}

	public function encode(){
		$this->putByte($this->windowID);
		//MPMPESCore InventoryType constants -> 1.8 window type strings
		switch($this->inventoryType){
			case 0: //CHEST
				$type = "minecraft:chest";
				break;
			case 3: //FURNACE
				$type = "minecraft:furnace";
				break;
			case 5: //WORKBENCH
				$type = "minecraft:crafting_table";
				break;
			case 7: //BREWING_STAND
				$type = "minecraft:brewing_stand";
				break;
			case 8: //ANVIL
				$type = "minecraft:anvil";
				break;
			case 9: //ENCHANT_TABLE
				$type = "minecraft:enchanting_table";
				break;
			case 10: //DISPENSER
				$type = "minecraft:dispenser";
				break;
			case 11: //DROPPER
				$type = "minecraft:dropper";
				break;
			case 12: //HOPPER
				$type = "minecraft:hopper";
				break;
			default:
				$type = "minecraft:chest";
				break;
		}
		$this->putString($type);
		$this->putString($this->windowTitle);
		$this->putByte($this->slots);
		//1.8 has no useTitle byte; entityId only follows for horse inventories
		if($type === "EntityHorse"){
			$this->putInt($this->entityId);
		}
	}

	public function decode(){

	}
}