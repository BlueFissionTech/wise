<?php

namespace BlueFission\Wise\Kernel;

use BlueFission\Arr;

class ProcessManager {
    protected $_processes;

    public function initialize() {
        // Initialize process management
        $this->_processes = new Arr();
    }

    public function createProcess($invocation, $args = []) {
        $process = new Process($invocation, $args);
        $this->_processes->set(uniqid(), $process);
        return $process;
    }
}