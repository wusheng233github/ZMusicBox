# ZMusicBox
Play music in your server using noteblocks!

## Requirements
 - Your server software (possibly PocketMine-MP or its forks) supports noteblock
 - Compatible with **PocketMine-MP API version 2.0.0**
 - A player standing next to the noteblock with audio on and a working internet connection
 - Songs must be in a valid **Classic NBS** format and have a .nbs file extension

## How to use
1) Place the phar in your plugins folder of the server
2) Run the server
3) Stop the server
4) Place .nbs files in the /plugins/ZMusicBox/songs directory of the server
5) Run the server
6) Place a noteblock

## Commands

`/music start` Start playing a random song

`/music stop` Stop playing the current song

`/music next` Start playing a random song

`/music loop [on|off]` Switch to single loop mode

## API
ZMusicBox is also accessible from its API:
 - Switch to the Next Song
```php
$this->getServer()->getPluginBase()->getPlugin("ZMusicBox")->StartNewTask();
```
 - Stop the music
```php
$zmusicbox = $this->getServer()->getPluginBase()->getPlugin("ZMusicBox");
$zmusicbox->taskHandler->cancel();
$zmusicbox->taskHandler = null;
```

## Other Information
 - Use Minecraft Note Block Studio to convert midi files into nbs files.
Website: http://www.stuffbydavid.com/mcnbs
 - Please do not use this code nor these algorithms for other plugins
