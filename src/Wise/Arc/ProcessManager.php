<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\MemoryManager;
use BlueFission\Arr;

class ProcessManager {
    protected $_processes;
    protected $_memoryManager;

    public function initialize() {
        // Initialize process management
        $this->_processes = new Arr();
    }

    public function setMemoryManager(MemoryManager $memoryManager) {
        $this->_memoryManager = $memoryManager;
    }

    public function createProcess($command, $input = null) {
        $process = new Process($command, $input);
        $pid = uniqid();
        $this->_processes->set($pid, $process);
        $this->_memoryManager->register($process, $pid);

        return $process;
    }
}