<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Wise\Sys\Utl\KeyInputUtil;

class KeyInputManager {

	public function initialize() {
		// Initialize the key input manager
	}

	public function capture() {
		// Listen for key input
		return KeyInputUtil::listen();
	}
}