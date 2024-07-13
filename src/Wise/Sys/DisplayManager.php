<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Wise\Sys\Drivers\IDisplayDriver;

class DisplayManager {

	protected $_driver;

	public function __construct( IDisplayDriver $driver ) {
		$this->_driver = $driver;
	}

	public function initialize() {
		// Initialize the display
	}

	public function send( $data ) {
		$this->_driver->handle( $data );
	}
}