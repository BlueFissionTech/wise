<?php

namespace BlueFission\Wise\Sys\Utl;

use BlueFission\Connections\Stdio;

class ConsoleAudioUtil {

	const BEEP = "\x07";

    private static $_stdio;
    private static $_helper;

    public static function init(Stdio $stdio, IConsoleAudioHelper $helper = null) {
        self::$_stdio = $stdio;
        self::$_helper = $helper ?? PyConsoleAudioHelper::class;
    }

    public static function send($data) {
    	// $validate data
    	$data = (string) $data;
    	$data = preg_replace('/[^0-9A-Za-z\s]/', '', $data);

	    if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
	        // Windows
	        echo $data; // ASCII Bell
	    } elseif (strncasecmp(PHP_OS, 'DAR', 3) == 0 || strncasecmp(PHP_OS, 'MAC', 3) == 0) {
	        // macOS
	        exec("printf '{$data}'");
	    } else {
	        // Linux
	        exec("echo -e '{$data}'");
	    }
    }
	
	public static function beep() {
		self::send(self::BEEP);
	}

	public function tts($text, $desiredVoice = "") {
	    self::$_helper::tts($text, $desiredVoice);
	}

	public function dtmf($digit, $duration = 0.5) {
	   	self::$_helper::dtmf($digit, $duration);
	}
}