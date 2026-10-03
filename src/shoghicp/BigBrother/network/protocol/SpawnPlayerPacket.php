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
use shoghicp\BigBrother\utils\Binary;

class SpawnPlayerPacket extends Packet{

	public $eid;
	public $uuid;
	public $x;
	public $y;
	public $z;
	public $yaw;
	public $pitch;
	public $item;
	public $metadata;

	public function pid(){
		return 0x0c;
	}

	public function encode(){
		$this->putVarInt($this->eid);
		//1.8 sends the UUID as 16 raw bytes, NOT a length-prefixed string
		$hex = str_replace("-", "", $this->uuid);
		for($i = 0; $i < 16; ++$i){
			$this->putByte(hexdec(substr($hex, $i * 2, 2)));
		}
		$this->putInt(intval($this->x * 32));
		$this->putInt(intval($this->y * 32));
		$this->putInt(intval($this->z * 32));
		//1.8 angles are single bytes: degrees * 256 / 360
		$this->putByte(((int) ($this->yaw * 256 / 360)) & 0xFF);
		$this->putByte(((int) ($this->pitch * 256 / 360)) & 0xFF);
		$this->putShort($this->item);
		$this->put(Binary::writeMetadata($this->metadata));
	}

	public function decode(){

	}
}