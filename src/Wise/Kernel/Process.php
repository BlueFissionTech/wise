<?php

namespace BlueFission\Wise\Kernel;

class Process {
    protected $_invocation;
    protected $_args;

    public function __construct($invocation, $args) {
        $this->_invocation = $invocation;
        $this->_args = $args;
    }

    public function canHandle($request) {
        // Check if this process can handle the request
        return strpos($request, $this->_invocation) === 0;
    }

    public function handle($request) {
        // Handle the request
        return "Process handling request: " . $request;
    }
}
