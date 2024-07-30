<?php

namespace BlueFission\Wise\Sys\Drivers;

use BlueFission\Wise\Sys\Utl\ConsoleAudioUtil;

class ConsoleAudioDriver implements IAudioDriver {
	
	private $_consoleAudioUtil;

	public function __construct(ConsoleAudioUtil $consoleAudioUtil) {
		$this->_consoleAudioUtil = $consoleAudioUtil;
	}

	public function play($file) {
		$this->_consoleAudioUtil->play($file);
	}

	public function stop() {
		$this->_consoleAudioUtil->stop();
	}

	public function pause() {
		$this->_consoleAudioUtil->pause();
	}

	public function resume() {
		$this->_consoleAudioUtil->resume();
	}

	public function volume($level) {
		$this->_consoleAudioUtil->volume($level);
	}

	public function mute() {
		$this->_consoleAudioUtil->mute();
	}

	public function unmute() {
		$this->_consoleAudioUtil->unmute();
	}

	public function isMuted() {
		return $this->_consoleAudioUtil->isMuted();
	}

	public function isPlaying() {
		return $this->_consoleAudioUtil->isPlaying();
	}

	public function isPaused() {
		return $this->_consoleAudioUtil->isPaused();
	}

	public function isStopped() {
		return $this->_consoleAudioUtil->isStopped();
	}

	public function isVolume($level) {
		return $this->_consoleAudioUtil->isVolume($level);
	}
}