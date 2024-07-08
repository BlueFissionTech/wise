<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Utils\Mem;
use BlueFission\Behavioral\Behavioral;
use BlueFission\Behavioral\Behaviors\State;

class MemoryManager {
    protected $_threshold;
    protected $_interval;
    protected $_async;

    public function __construct($threshold = 300, $interval = 60, $async) {
        $this->_threshold = $threshold;
        $this->_interval = $interval;
        $this->_async = $async;
        Mem::threshold($threshold);
    }

    public function initialize() {
        // Start async task to monitor memory
        $this->_async->do([$this, 'monitorMemory'])->then(function() {
            $this->initialize();
        });
    }

    public function monitorMemory() {
        Mem::flush();  // Clean up unused objects
        $this->sleepIdleProcesses();
        return $this->_interval;  // Re-run this task after the interval
    }

    public function register($object, $id = null) {
        Mem::register($object, $id);
    }

    public function unregister($id) {
        Mem::unregister($id);
    }

    public function get($id) {
        return Mem::get($id);
    }

    public function sleep($id) {
        Mem::sleep($id);
    }

    public function wakeup($id) {
        Mem::wakeup($id);
    }

    public function sleepAll() {
        Mem::sleepAll();
    }

    protected function sleepIdleProcesses() {
        $unused = Mem::audit();
        foreach ($unused as $id => $info) {
            $object = Mem::get($id);
            if ($object instanceof Behavioral && $object->is(State::IDLE)) {
                Mem::sleep($id);
            }
        }
    }
}
