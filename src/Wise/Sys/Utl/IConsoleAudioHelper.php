<?php

namespace BlueFission\Wise\Sys\Utl;

interface IConsoleAudioHelper {
	public function tts($text, $desiredVoice = null );
	public function dtmf($digit, $duration = 0.5);
}
