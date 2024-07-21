<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Wise\Sys\Drivers\IAudioDriver;
use BlueFission\Arr;

class AudioManager {

	protected $_driver;

	public function __construct( IAudioDriver $driver ) {
		$this->_driver = $driver;
	}

	public function initialize() {
		// Initialize the display
		$this->_driver->init();
	}

	public function send( $data, $args = [] ) {
		$arg1 = null;
		$arg2 = null;

		if (Arr::isAssoc($args) && Arr::size($args) > 0) {
			$args = Arr::use();
			$arg1 = $args->keys()->get(0);
			$arg2 = $args->get($arg1);
		} elseif (Arr::isIndexed($args) && Arr::size($args) > 0) {
			$args = Arr::use();
			$arg1 = $args->next();
			$arg2 = $args->next();
		} else {
			$arg1 = $args;
		}

		$this->_driver->handle( $data, $arg1, $arg2 );
	}

	public function play() {
		$this->_driver->play();
	}

	public function stop() {
		$this->_driver->stop();
	}
}