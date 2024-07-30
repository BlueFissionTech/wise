<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Arr;

class DisplayManager {

	protected $_driver;

	public function __construct( IDisplayDriver $driver ) {
		$this->_driver = $driver;
	}

	public function initialize() {
		// Initialize the display
		$this->_driver->init();
	}

	public function update() {
		$this->_driver->update();
	}

	public function send( $data, $args = [] ) {
		$this->display( $data, $args );
		$processed = $this->_driver->getContent();
		$this->_driver->send( Arr::pop($processed) );
	}

	public function display( $data, $args = [] ) {
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

	public function getSize(): array
	{
		return $this->_driver->getTerminalSize();
	}

	public function draw() {
		$this->_driver->draw();
	}

	public function print() {
		$this->_driver->print();
	}

	public function clear() {
		$this->_driver->clear();
	}

	public function clearScreen() {
		$this->_driver->clearScreen();
	}
}