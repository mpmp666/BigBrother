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

namespace shoghicp\BigBrother\network;

use pocketmine\item\Item;
use shoghicp\BigBrother\utils\Binary;

abstract class Packet extends \stdClass{

	protected $buffer;
	protected $offset = 0;

	protected function get($len){
		if($len < 0){
			$this->offset = strlen($this->buffer) - 1;

			return "";
		}elseif($len === true){
			return substr($this->buffer, $this->offset);
		}

		$buffer = "";
		for(; $len > 0; --$len, ++$this->offset){
			$buffer .= @$this->buffer[$this->offset];
		}

		return $buffer;
	}

	protected function getLong(){
		return Binary::readLong($this->get(8));
	}

	protected function getInt(){
		return Binary::readInt($this->get(4));
	}

	protected function getPosition(&$x, &$y, &$z){
		$int1 = $this->getInt();
		$int2 = $this->getInt();

		//1.8 packs Position as x[26] | y[12] | z[26] big-endian into one long.
		//int1 holds x and the TOP 6 bits of y; int2 holds the BOTTOM 6 bits of y
		//(in its top 6 bits) and z. (Upstream BigBrother mis-combined these and
		//sign-extended y as if it were 6 bits, corrupting y for any value > 31.)
		$x = $int1 >> 6;
		$y = (($int1 & 0x3F) << 6) | (($int2 >> 26) & 0x3F);
		$z = $int2 & 0x3FFFFFF;

		if(PHP_INT_MAX > 0x7FFFFFFF){
			$x = $x << 38 >> 38;
			$y = $y << 52 >> 52;
			$z = $z << 38 >> 38;
		}else{
			$x = $x << 6 >> 6;
			$y = $y << 20 >> 20;
			$z = $z << 6 >> 6;
		}
	}

	protected function getFloat(){
		return Binary::readFloat($this->get(4));
	}

	protected function getDouble(){
		return Binary::readDouble($this->get(8));
	}

	/**
	 * @return Item
	 */
	protected function getSlot(){
		//must be a SIGNED read: an empty hand is -1, which an unsigned short
		//would report as 65535 and then count/damage bytes would be misread
		$itemId = Binary::readSignedShort($this->get(2));
		if($itemId === -1){ //Empty
			return Item::get(Item::AIR, 0, 0);
		}else{
			$count = $this->getByte();
			$damage = $this->getShort();
			//1.8 slot NBT: a single 0x00 byte means "no NBT", otherwise a full
			//TAG_Compound follows (no length prefix). Skip it if present.
			if(!$this->feof()){
				$this->skipNbt();
			}
			return Item::get($itemId, $damage, $count);
		}
	}

	/**
	 * Skips over a 1.8 "optional NBT" field: either a lone 0x00 byte, or a
	 * complete unnamed-compound NBT structure (big-endian).
	 */
	protected function skipNbt(){
		$type = $this->getByte();
		if($type === 0){
			return; //no NBT present
		}
		//tag name
		$nameLen = $this->getShort();
		$this->get($nameLen);
		$this->skipNbtPayload($type);
	}

	protected function skipNbtPayload($type){
		switch($type){
			case 1: $this->get(1); break;
			case 2: $this->get(2); break;
			case 3: $this->get(4); break;
			case 4: $this->get(8); break;
			case 5: $this->get(4); break;
			case 6: $this->get(8); break;
			case 7: $n = $this->getInt(); if($n > 0){ $this->get($n); } break;
			case 8: $n = $this->getShort(); if($n > 0){ $this->get($n); } break;
			case 9:
				$elem = $this->getByte();
				$n = $this->getInt();
				for($i = 0; $i < $n and $elem !== 0; ++$i){
					$this->skipNbtPayload($elem);
				}
				break;
			case 10:
				while(!$this->feof()){
					$t = $this->getByte();
					if($t === 0){
						break;
					}
					$l = $this->getShort();
					$this->get($l);
					$this->skipNbtPayload($t);
				}
				break;
			case 11: $n = $this->getInt(); if($n > 0){ $this->get($n * 4); } break;
		}
	}

	protected function putSlot(Item $item){
		if($item->getId() === 0){
			$this->putShort(-1);
		}else{
			$this->putShort($item->getId());
			$this->putByte($item->getCount());
			$this->putShort($item->getDamage());
			//1.8 optional NBT: a single 0x00 (TAG_End) marks "no NBT". Without
			//this byte the client misreads the next field as an NBT tag.
			$this->putByte(0);
		}
	}

	protected function getShort(){
		return Binary::readShort($this->get(2));
	}

	protected function getTriad(){
		return Binary::readTriad($this->get(3));
	}

	protected function getLTriad(){
		return Binary::readTriad(strrev($this->get(3)));
	}

	protected function getByte(){
		if($this->offset >= strlen($this->buffer)){
			$this->offset++;
			return 0;
		}
		return ord($this->buffer[$this->offset++]);
	}

	protected function getString(){
		return $this->get($this->getVarInt());
	}

	protected function getVarInt(){
		return Binary::readVarInt($this->buffer, $this->offset);
	}

	protected function feof(){
		return !isset($this->buffer[$this->offset]);
	}

	protected function put($str){
		$this->buffer .= $str;
	}

	protected function putLong($v){
		$this->buffer .= Binary::writeLong($v);
	}

	protected function putInt($v){
		$this->buffer .= Binary::writeInt($v);
	}

	protected function putPosition($x, $y, $z){
		$int2 = ($z & 0x3FFFFFF); //26 bits
		$int2 |= ($y & 0x3F) << 26; //6 bits
		$int1 = ($y & 0xFC0) >> 6; //6 bits
		$int1 |= ($x & 0x3FFFFFF) << 6; //26 bits
		$this->buffer .= Binary::writeInt($int1) . Binary::writeInt($int2);
	}

	protected function putFloat($v){
		$this->buffer .= Binary::writeFloat($v);
	}

	protected function putDouble($v){
		$this->buffer .= Binary::writeDouble($v);
	}

	protected function putShort($v){
		$this->buffer .= Binary::writeShort($v);
	}

	protected function putTriad($v){
		$this->buffer .= Binary::writeTriad($v);
	}

	protected function putLTriad($v){
		$this->buffer .= strrev(Binary::writeTriad($v));
	}

	protected function putByte($v){
		$this->buffer .= chr($v);
	}

	protected function putString($v){
		$this->putVarInt(strlen($v));
		$this->put($v);
	}

	protected function putVarInt($v){
		$this->buffer .= Binary::writeVarInt($v);
	}

	public abstract function pid();

	protected abstract function encode();

	protected abstract function decode();

	public function write(){
		$this->buffer = "";
		$this->offset = 0;
		$this->encode();
		return Binary::writeVarInt($this->pid()) . $this->buffer;
	}

	public function read($buffer, $offset = 0){
		$this->buffer = $buffer;
		$this->offset = $offset;
		$this->decode();
	}

}