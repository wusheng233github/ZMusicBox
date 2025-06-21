<?php
namespace ZMusicBox;

use pocketmine\block\NoteBlock;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\BatchPacket;
use pocketmine\network\protocol\BlockEventPacket;
use pocketmine\network\protocol\TextPacket;
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
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!is_dir($this->getPluginDir())){
			mkdir($this->getPluginDir());
		}
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		if(!$this->CheckMusic()){
			$this->getLogger()->info("§bPlease put in nbs files!!!");
		}else{
			$this->StartNewTask();
		}
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
		if($cmd->getName() != "music" || !isset($args[0])) {
			return false;
		}
		if(!$cmd->testPermission($sender)) {
			return true;
		}
		switch($args[0]){
			case "next":
			case "skip":
				$this->StartNewTask(true);
				$sender->sendMessage(TextFormat::GREEN."Switched to next song");
				break;
			case "stop":
			case "pause":
				if($this->taskHandler !== null) {
					$this->taskHandler->cancel();
					$this->taskHandler = null;
				}
				$sender->sendMessage(TextFormat::GREEN."Song Stopped");
				break;
			case "start":
			case "begin":
			case "resume":
				$this->StartNewTask();
				$sender->sendMessage(TextFormat::GREEN."Song Started");
				break;
			case 'loop':
				switch(isset($args[1]) ? $args[1] : "") {
					case 'on':
						$this->loop = true;
						break;
					case 'off':
						$this->loop = false;
						break;
					default:
						$this->loop = !$this->loop;
						break;
				}
				$sender->sendMessage(TextFormat::GREEN."Single loop is ".($this->loop ? "on" : "off"));
				break;
			default:
				return false;
		}
		return true;
	}

	public function CheckMusic(){
		if($this->getDirCount($this->getPluginDir()) > 0 and $this->RandomFile($this->getPluginDir(),"nbs")){
			return true;
		}
		return false;
	}

	public function getDirCount($PATH){
		$num = sizeof(scandir($PATH));
		$num = ($num>2)?$num-2:0;
		return $num;
	}

	public function getPluginDir(){
		return $this->getDataFolder() . 'songs/';
	}

	public function getRandomMusic(){
		$filepath = $this->RandomFile($this->getPluginDir(),"nbs");
		if($filepath){
			if(isset($this->songsloaded[$filepath])) {
				$this->songsloaded[$filepath]->tick = 0;
				return $this->songsloaded[$filepath];
			} else {
				$api = new NoteBoxAPI($this,$filepath);
				$this->songsloaded[$filepath] = $api;
				return $api;
			}
		}
		return false;
	}
	
	Public function RandomFile($folder='', $extensions='.*'){
		$folder = trim($folder);
		$folder = ($folder == '') ? './' : $folder;
		if (!is_dir($folder)){
			return false;
		}
		$files = array();
		if ($dir = @opendir($folder)){
			while($file = readdir($dir)){
				if (!preg_match('/^\.+$/', $file) and
					preg_match('/\.('.$extensions.')$/', $file)){
					$files[] = $file;
				}
			}
			closedir($dir);
		}else{
			return false;
		}
		if (count($files) == 0){
			return false;
		}
		mt_srand((double)microtime()*1000000);
		$rand = mt_rand(0, count($files)-1);
		if (!isset($files[$rand])){
			return false;
		}
		if(function_exists("iconv")){
			$rname = iconv('gbk','UTF-8',$files[$rand]);
		}else{
			$rname = $files[$rand];
		}
		$this->name = str_replace('.nbs', '', $rname);
		return $folder . $files[$rand];
	}

	public function getNearbyNoteBlock($x,$y,$z,$world){ // TODO: 性能问题
		$nearby = [];
		$range = $this->getConfig()->get('range', 3);
		$minX = $x - $range;
		$maxX = $x + $range;
		$minY = $y - $range;
		$maxY = $y + $range;
		$minZ = $z - $range;
		$maxZ = $z + $range;

		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					$v3 = new Vector3($x, $y, $z);
					$block = $world->getBlock($v3);
					if($block instanceof NoteBlock){
						$nearby[] = $block;
					}
				}
			}
		}
		return $nearby;
	}

	public function Play(array $sounds){
		$batchmode = $this->getConfig()->get('batch', 0);
		foreach($this->getServer()->getOnlinePlayers() as $onlineplayer){
			$noteblocks = $this->getNearbyNoteBlock($onlineplayer->x,$onlineplayer->y,$onlineplayer->z,$onlineplayer->getLevel());
			usort($noteblocks, function(Vector3 $a, Vector3 $b) use($onlineplayer) {
				$a = $a->distance($onlineplayer);
				$b = $b->distance($onlineplayer);
				if($a > $b) {
					return 1;
				} else if($a < $b) {
					return -1;
				} else {
					return 0;
				}
			});
			if(empty($noteblocks)){
				continue;
			}
			$batch = [];
			$pk = new TextPacket();
			$pk->type = TextPacket::TYPE_POPUP;
			$pk->message = "";
			if($this->getConfig()->get('progressbar', true)) {
				$length = 30;
				$progress = max(ceil(min($this->song->tick / $this->song->length * $length, $length)) - 1, 0);
				$pk->message = TextFormat::LIGHT_PURPLE . str_repeat("=", $progress) . ">" . TextFormat::GRAY . str_repeat('-', $length - $progress - 1);
			}
			$pk->source = "§b|->§6Now Playing: §a" . ($this->song->name != "" ? $this->song->name : $this->name) . "§b<-|";
			$batch[] = $pk;
			foreach($sounds as $sound) {
				if(next($noteblocks) === false) {
					reset($noteblocks);
				}
				$block = current($noteblocks);
				if($block === false) {
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
			if($batchmode == 1) {
				$pk = new BatchPacket();
				foreach($batch as $packet) {
					$packet->encode();
					$pk->payload .= Binary::writeInt(strlen($packet->buffer)) . $packet->buffer;
				}
				$pk->payload = zlib_encode($pk->payload, ZLIB_ENCODING_DEFLATE, 5);
				$onlineplayer->dataPacket($pk);
			} else {
				foreach($batch as $packet) {
					$onlineplayer->batchDataPacket($packet); // 有异步压缩
				}
			}
		}
	}

	public function StartNewTask($noloop = false){
		if(!$this->loop || $noloop) {
			$this->song = $this->getRandomMusic();
		} else {
			$this->song->tick = 0;
		}
		if($this->taskHandler !== null) {
			$this->taskHandler->cancel();
		}
		$this->MusicPlayer = new MusicPlayer($this);
		$this->taskHandler = $this->getServer()->getScheduler()->scheduleRepeatingTask($this->MusicPlayer, 2990 / $this->song->speed);
	}
}

class MusicPlayer extends Task{
	protected $plugin;

	public function __construct(ZMusicBox $plugin){
		$this->plugin = $plugin;
	}
	
	public function onRun($CT){ // TODO: 过载
		if(isset($this->plugin->song->sounds[$this->plugin->song->tick])){
			$this->plugin->Play($this->plugin->song->sounds[$this->plugin->song->tick]);
		}
		$this->plugin->song->tick++;
		if($this->plugin->song->tick > $this->plugin->song->length){
			$this->plugin->StartNewTask();
		}
	}
}