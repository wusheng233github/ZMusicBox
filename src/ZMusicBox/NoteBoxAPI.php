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

		$this->length = $this->getLShort(); // TODO: 立体声
		$version = 0;
		if($this->length == 0) {
			$version = $this->getByte();
			$this->getByte();
			if($version >= 3) {
				$this->length = $this->getLShort();
			}
		}
		$layerCount = $this->getLShort();
		$this->name = $this->getString();
		$this->getString();
		$this->getString();
		$this->getString();
		$this->speed = $this->getLShort();
		$this->getByte();
		$this->getByte();
		$this->getByte();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getInt();
		$this->getString();

		if($version >= 4) {
			$this->getByte();
			$this->getByte();
			$this->getLShort();
		}

		$tick = -1;
		$sounds = [];
		while(($tickJump = $this->getLShort()) !== 0) {
			$tick += $tickJump;

			$layerId = -1;
			$layerSounds = [];

			while(($layerJump = $this->getLShort()) !== 0) {
				$layerId += $layerJump;

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

				$pitch = $this->getByte() - 33; // TODO

				if($version >= 4) {
					$this->getByte();
					$this->getByte();
					$this->getLShort();
				}

				$layerSounds[$layerId] = [$pitch, $type];
			}

			$sounds[$tick] = $layerSounds;
		}

		if($version < 3) {
			$this->length = $tick;
		}

		$this->sounds = $sounds;
	}

	public function getString(){
		return $this->get(unpack("I", $this->get(4))[1]);
	}
}