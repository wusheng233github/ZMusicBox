<?php
namespace ZMusicBox;

use pocketmine\block\NoteBlock;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\TranslationContainer;
use pocketmine\level\Level;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\BatchPacket;
use pocketmine\network\protocol\BlockEventPacket;
use pocketmine\network\protocol\TextPacket;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\Binary;

class ZMusicBox extends PluginBase implements Listener{
	/** @var null|NoteBoxAPI */
	public $song;
	/** @var NoteBoxAPI[] */
	public $songsloaded = [];
	/** @var MusicPlayer */
	public $MusicPlayer;
	public $name;
	/** @var null|\pocketmine\scheduler\TaskHandler */
	public $taskHandler;
	public $loop = false;

	public function onEnable(){
		$this->saveDefaultConfig();
		if(!is_dir($this->getSongsDir())){
			mkdir($this->getSongsDir());
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!$this->CheckMusic()){
			$this->getLogger()->info("§bPlease put in nbs files!!!");
		}else{
			$this->StartNewTask();
		}
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
		if($cmd->getName() != "music" or !isset($args[0])){
			return false;
		}
		if(!$cmd->testPermission($sender)){
			return true;
		}
		switch($args[0]){
			case "next":
			case "skip":
				if(!$sender->hasPermission("ZMusicBox.music.next")){
					$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
					return true;
				}
				$this->StartNewTask(true);
				$sender->sendMessage(TextFormat::GREEN . "Switched to next song");
				break;
			case "stop":
			case "pause":
				if(!$sender->hasPermission("ZMusicBox.music.stop")){
					$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
					return true;
				}
				if($this->taskHandler !== null){
					$this->taskHandler->cancel();
					$this->taskHandler = null;
				}
				$sender->sendMessage(TextFormat::GREEN . "Song Stopped");
				break;
			case "start":
			case "begin":
			case "resume":
				if(!$sender->hasPermission("ZMusicBox.music.start")){
					$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
					return true;
				}
				$this->StartNewTask();
				$sender->sendMessage(TextFormat::GREEN . "Song Started");
				break;
			case "loop":
				switch(isset($args[1]) ? $args[1] : ""){
					case "on":
						if(!$sender->hasPermission("ZMusicBox.music.loop.on")){
							$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
							return true;
						}
						$this->loop = true;
						break;
					case "off":
						if(!$sender->hasPermission("ZMusicBox.music.loop.off")){
							$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
							return true;
						}
						$this->loop = false;
						break;
					default:
						if(!$sender->hasPermission("ZMusicBox.music.loop")){
							$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
							return true;
						}
						$this->loop = !$this->loop;
						break;
				}
				$sender->sendMessage(TextFormat::GREEN . "Single loop is " . ($this->loop ? "on" : "off"));
				break;
			default:
				return false;
		}
		return true;
	}

	public function CheckMusic(){
		if($this->getDirCount($this->getSongsDir()) > 0 and $this->randomFile($this->getSongsDir(), "nbs")){
			return true;
		}
		return false;
	}

	public function getDirCount($path){
		$num = sizeof(scandir($path));
		$num = ($num > 2) ? $num - 2 : 0;
		return $num;
	}

	public function getSongsDir(){
		return $this->getDataFolder() . "songs/";
	}

	/**
	 * @param false|string|null $filepath
	 * @return false|NoteBoxAPI
	 */
	public function getMusic($filepath = null){
		if($filepath === null) {
			$filepath = $this->randomFile($this->getSongsDir(), 'nbs');
		}
		if($filepath){
			if(isset($this->songsloaded[$filepath])){
				$this->getLogger()->debug("Loaded: $filepath");
				$this->songsloaded[$filepath]->tick = 0;
				return $this->songsloaded[$filepath];
			}else{
				$this->getLogger()->debug("File: $filepath");
				$api = new NoteBoxAPI($this,$filepath);
				$this->songsloaded[$filepath] = $api;
				return $api;
			}
		}
		if(($randomkey = array_rand($this->songsloaded)) !== null){
			$this->getLogger()->debug($filepath);
			return $this->songsloaded[$randomkey];
		}
		return false;
	}

	public function randomFile($folder='', $extensions='.*'){ // XXX
		$folder = trim($folder);
		$folder = ($folder == '') ? './' : $folder;
		if(!is_dir($folder)){
			return false;
		}
		$files = array();
		if($dir = @opendir($folder)){
			while($file = readdir($dir)){
				if (!preg_match('/^\.+$/', $file) and
					preg_match('/\.(' . $extensions . ')$/', $file)){
					$files[] = $file;
				}
			}
			closedir($dir);
		}else{
			return false;
		}
		if(count($files) == 0){
			return false;
		}
		mt_srand((double) microtime() * 1000000);
		$rand = mt_rand(0, count($files) - 1);
		if(!isset($files[$rand])){
			return false;
		}
		if(function_exists("iconv")){
			$rname = iconv("gbk", "UTF-8", $files[$rand]);
		}else{
			$rname = $files[$rand];
		}
		$this->name = str_replace(".nbs", "", $rname);
		return $folder . $files[$rand];
	}

	public function getNearbyNoteBlock($x, $y, $z, Level $world, $range, $max = -1){ // XXX
		$nearby = [];
		$num = 0;
		for($layer = 0; $layer <= $range; $layer++){
			for($i = -$layer; $i <= $layer; $i++){
				for($j = -$layer; $j <= $layer; $j++){
					for($k = -$layer; $k <= $layer; $k++){
						if(abs($i) + abs($j) + abs($k) <= $layer){
							$blockX = $x + $i;
							$blockY = $y + $j;
							$blockZ = $z + $k;
							$block = $world->getBlock(new Vector3($blockX, $blockY, $blockZ));
							if($block instanceof NoteBlock){
								$nearby[] = $block;
								if($max != -1 and ++$num > $max){
									break 4;
								}
							}
						}
					}
				}
			}
		}
		return $nearby;
	}

	public function Play(array $sounds){
		$batchmode = $this->getConfig()->get('batch', 0);
		$progressbar = '';
		if($this->getConfig()->get('progressbar', true)){
			$length = 30;
			$progress = max(ceil(min($this->song->tick / $this->song->length * $length, $length)) - 1, 0);
			$progressbar = TextFormat::LIGHT_PURPLE . str_repeat("=", $progress) . ">" . TextFormat::GRAY . str_repeat("-", $length - $progress - 1);
		}
		$songname = '§b|->§6Now Playing: §a' . ($this->song->name != "" ? $this->song->name : $this->name) . '§b<-|';
		foreach($this->getServer()->getOnlinePlayers() as $onlineplayer){
			$noteblocks = $this->getNearbyNoteBlock($onlineplayer->x, $onlineplayer->y, $onlineplayer->z, $onlineplayer->getLevel(), $this->getConfig()->get('range', 3), count($sounds));
			if(empty($noteblocks)){
				continue;
			}
			$batch = [];
			if($onlineplayer->hasPermission('ZMusicBox.popup')){
				$pk = new TextPacket();
				$pk->type = TextPacket::TYPE_POPUP;
				$pk->source = $songname;
				$pk->message = '';
				if($onlineplayer->hasPermission('ZMusicBox.popup.progress')){
					$pk->message = $progressbar;
				}
				$batch[] = $pk;
			}
			if($onlineplayer->hasPermission('ZMusicBox.canhear')){
				foreach($sounds as $sound){
					if(next($noteblocks) === false){
						reset($noteblocks);
					}
					$block = current($noteblocks);
					if($block === false){
						continue;
					}
					$pk = new BlockEventPacket();
					$pk->x = $block->x;
					$pk->y = $block->y;
					$pk->z = $block->z;
					$pk->case1 = $sound[1]; // type
					$pk->case2 = $sound[0]; // sound
					$batch[] = $pk;
				}
			}
			if($batchmode == 1){
				$pk = new BatchPacket();
				foreach($batch as $packet){
					$packet->encode();
					$pk->payload .= Binary::writeInt(strlen($packet->buffer)) . $packet->buffer;
				}
				$pk->payload = zlib_encode($pk->payload, ZLIB_ENCODING_DEFLATE, 5);
				$onlineplayer->dataPacket($pk);
			}else{
				foreach($batch as $packet){
					$onlineplayer->batchDataPacket($packet);
				}
			}
		}
	}

	public function StartNewTask($noloop = false){
		if(!$this->loop or $noloop){
			$song = $this->getMusic();
			if($song === false){
				throw new \Exception("There is no song file in the plugins/ZMusicBox/songs directory"); // FIXME
			}
			$this->song = $song;
		}
		$this->song->tick = 0;
		if($this->taskHandler !== null){
			$this->taskHandler->cancel();
		}
		$this->MusicPlayer = new MusicPlayer($this);
		$this->taskHandler = $this->getServer()->getScheduler()->scheduleRepeatingTask($this->MusicPlayer, 2000 / $this->song->speed);
		$this->getLogger()->debug("speed: {$this->song->speed}");
	}
}
class MusicPlayer extends PluginTask{
	/** @var ZMusicBox */
	protected $owner;
	public function __construct(ZMusicBox $owner){
		$this->owner = $owner;
	}
	public function onRun($currentTick){ // TODO: 过载
		if(isset($this->owner->song->sounds[$this->owner->song->tick])){
			$this->owner->Play($this->owner->song->sounds[$this->owner->song->tick]);
		}
		if(++$this->owner->song->tick > $this->owner->song->length){
			$this->owner->StartNewTask();
		}
	}
}