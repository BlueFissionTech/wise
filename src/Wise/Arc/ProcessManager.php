<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\MemoryManager;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class ProcessManager {
    protected $_processes;
    protected $_memoryManager;

    public function __construct()
    {
        $this->initialize();
    }

    public function initialize() {
        // Initialize process management
        $this->_processes = new Arr();
    }

    public function setMemoryManager(MemoryManager $memoryManager) {
        $this->_memoryManager = $memoryManager;
    }

    public function createProcess($command, $input = null) {
        if (!$this->_processes instanceof Arr) {
            $this->initialize();
        }

        $process = new Process($command, $input);
        $pid = Str::uuid4();
        $this->_processes->set($pid, $process);
        if (Val::isNotNull($this->_memoryManager)) {
            $this->_memoryManager->register($process, $pid);
        }

        return $process;
    }
}
