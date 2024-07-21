<?php

namespace BlueFission\Wise\Sys\Utl;

class PyConsoleAudioHelper implements IConsoleAudioHelper extends ConsoleAudioHelper {

    private static $_scriptsDir;	

    public static function init($scriptsDir = null ) {
        self::$_scriptsDir = $scriptsDir ?? __DIR__ . '/../../../../scripts/';
    }

	public function tts($text, $desiredVoice = "") {
	    $text = escapeshellarg($text);
	    $desiredVoice = escapeshellarg($desiredVoice);

	    if ( $desiredVoice == self::CUSTOM_VOICE ) {
	    	$command = "tts_custom.py $text";
	    } else {
	    	$command = "tts_pyttsx3.py $text $desiredVoice";
	    }

	    $command = 'python ' . self::$_scriptsDir .	$command;
	    
	    // Execute the command
	    exec($command, $output, $return_var);

	    if ($return_var !== 0) {
	        echo "Error generating speech.";
	    }
	}

	public function dtmf($digit, $duration = 0.5) {
	    $duration = escapeshellarg($duration);
	    $digit = escapeshellarg($digit);
	    $command = "dtmf.py $digit $duration";

	    $command = 'python ' . self::$_scriptsDir .	$command;

	    // Execute the command
	    exec($command, $output, $return_var);

	    if ($return_var !== 0) {
	        echo "Error generating or playing DTMF tone.";
	    }
	}
}