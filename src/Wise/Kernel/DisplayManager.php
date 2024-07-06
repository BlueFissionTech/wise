<?php

namespace BlueFission\Wise\Kernel\DisplayManager;

use BlueFission\Wise\Sys\Drivers\IDisplayDriver;

class DisplayManager {

	protected $_driver;

	public function __construct( IDisplayDriver $driver ) {
		$this->_driver = $driver;
	}

	public function send( $data ) {
		$this->_driver->handle( $data );
	}
}