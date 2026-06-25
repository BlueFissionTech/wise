<?php

namespace BlueFission\Wise\Arc;

class Base {

	protected $_kernel;

	public function __construct(Kernel $kernel) {
		$this->_kernel = $kernel;
	}

	protected function kernel(): Kernel {
		return $this->_kernel;
	}
}
