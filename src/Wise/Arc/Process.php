<?php

namespace BlueFission\Wise\Arc;

class Process {
    protected $_resource;
    protected $_command;

    public function __construct($resource, $command) {
        $this->_resource = $resource;
        $this->_command = $command;
    }

    public function canHandle($request) {
        // Check if this process can handle the request
        return strpos($request, $this->_resource) === 0;
    }

    public function handle($request) {
        
    }
}
