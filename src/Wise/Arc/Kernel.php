<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\{
    MemoryManager,
    FileSystemManager,
    DisplayManager
};
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\Async\Async;
use BlueFission\Services\Application as App;


class Kernel {
    protected $_processManager;
    protected $_commandProcessor;
    protected $_memoryManager;
    protected $_fileSystemManager;
    protected $_displayManager;
    protected $_interpreter;
    protected $_async;

    public function __construct(ProcessManager $processManager, CommandProcessor $commandProcessor, MemoryManager $memoryManager, FileSystemManager $fileSystemManager, IInterpreter $interpreter, DisplayManager $displayManager, Async $async) {
        $this->_processManager = $processManager;
        $this->_commandProcessor = $commandProcessor;
        $this->_memoryManager = $memoryManager;
        $this->_fileSystemManager = $fileSystemManager;
        $this->_displayManager = $displayManager;
        $this->_interpreter = $interpreter;
        $this->_async = $async;
    }

    public function boot() {
        // Initialize kernel components
        $this->_processManager->initialize();
        $this->_fileSystemManager->initialize();
        $this->_displayManager->initialize();
    }

    public function handleRequest($request)
    {
        // this method shoud determine if a request should be handled by the shell interpreter, the command processor, or directly by the kernel's internal commands
        
        // Determine if request is a command or a script
        if ($this->_interpreter->isValid($request)) {
            $this->handleScript($request);
        } else {
            $this->handleCommand($request);
        }
    }

    private function execute($request)
    {
        $response = $this->_commandProcessor->handle($request);
        $command = $this->_commandProcessor->lastCommand();
        $resource = $this->_commandProcessor->lastResource();
        $resourceObj = App::instance()->resolve($resource);

        $process = $this->_processManager->createProcess($resourceObj, $command);
        $this->_memoryManager->register($process);

        return $response;
    }

    public function handleScript($request) {
        $this->display("Not Implemented");
    }

    private function handleCommand($request) {
        // Dispatch request to appropriate service or process
        try {
            $process = $this->execute();
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
