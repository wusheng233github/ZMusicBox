<?php
namespace ZMusicBox;

use pocketmine\utils\BinaryStream;

class NoteBoxAPI extends BinaryStream{
	public $plugin;
	public $length;
	public $sounds = [];
	public $tick = 0;
	public $name;
	public $speed;

	public function __construct($plugin, $path){
		$this->plugin = $plugin;
		$fopen = fopen($path, "r");
		$this->buffer = fread($fopen, filesize($path));
		fclose($fopen);
		$this->length = $this->getLShort(); // TODO: 新NBS
		$height = $this->getLShort();
		$this->name = $this->getString();
		$this->getString();
		$this->getString();
		$this->getString();
		$this->speed = $this->getLShort();
		$this->getByte(); // TODO
		$this->getByte();
		$this->getByte();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getString();
		$tick = $this->getLShort() - 1;
		while(true){
			$sounds = [];
			$this->getLShort();
			while(true){
				switch($this->getByte()){
					case 1: // BASS
						$type = 4;
					break;
					case 2: // BASS_DRUM
						$type = 1;
					break;
					case 3: // CLICK
						$type = 2;
					break;
					case 4: // TABOUR
						$type = 3;
					break;
					default: // PIANO
						$type = 0;
					break;
				}
				/*
					const INSTRUMENT_PIANO = 0;
					const INSTRUMENT_BASS_DRUM = 1;
					const INSTRUMENT_CLICK = 2;
					const INSTRUMENT_TABOUR = 3;
					const INSTRUMENT_BASS = 4;
				*/
				if($height == 0){
					$pitch = $this->getByte() - 33;
				}elseif($height < 10){
					$pitch = $this->getByte() - 33 + $height;
				}else{
					$pitch = $this->getByte() - 48 + $height;
				}

				$sounds[] = [$pitch, $type];
				if($this->getLShort() == 0) break;
			}
			$this->sounds[$tick] = $sounds;
			if(($jump = $this->getLShort()) !== 0){
				$tick += $jump;
			}else{
				break;
			}
		}
	}
	
	public function getString(){
		return $this->get(unpack("I", $this->get(4))[1]);
	}

}
