<?php

namespace BlueFission\Wise\Kernel;

class Kernel {
    protected $_processManager;
    protected $_commandProcessor;
    protected $_memoryManager;
    protected $_fileSystem;
    protected $_displayManager;
    protected $_interpreter
    protected $_async;

    public function __construct(ProcessManager $processManager, CommandProcessor $commandProcessor, MemoryManager $memoryManager, FileSystem $fileSystem, IInterpreter $interpreter, DisplayManager $displayManager, Async $async) {
        $this->_processManager = $processManager;
        $this->_commandProcessor = $commandProcessor;
        $this->_memoryManager = $memoryManager;
        $this->_fileSystem = $fileSystem;
        $this->_displayManager = $displayManager;
        $this->_interpreter = $interpreter;
        $this->_async = $async;
    }

    public function boot() {
        // Initialize kernel components
        $this->_processManager->initialize();
        $this->_memoryManager->initialize();
        $this->_fileSystem->initialize();
        $this->_displayManager->initialize();
    }

    public function handleRequest($request)
    {
        // this method shoud determine if a request should be handled by the shell interpreter, the command processor, or directly by the kernel's internal commands
        
    }

    private function handleCommand($request) {
        // Dispatch request to appropriate service or process
        try {
            $response = $this->_commandProcessor->process($request);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->display($response);
    }

    private function handleCommandAsync($request) {
        // Dispatch request to appropriate service or process
        $this->_async->do((function() use ($request) {
            try {
                $response = $this->_commandProcessor->process($request);
            } catch (\Exception $e) {
                $response = $e->getMessage();
            }
        })->bindTo($this))->then((function($response) {
            $this->display($response);
        })->bindTo($this), function($error) {
            // Handle this somehow
        });
    }

    private function display($response) {
        $this->_displayManager->send($response);
    }

    public function shutdown() {
        // Shutdown kernel components
    }
}
