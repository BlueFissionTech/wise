<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\{
    MemoryManager,
    FileSystemManager,
    DisplayManager,
    KeyInputManager
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
    protected $_keyInputManager;
    protected $_interpreter;
    protected $_async;

    public function __construct(ProcessManager $processManager, CommandProcessor $commandProcessor, MemoryManager $memoryManager, FileSystemManager $fileSystemManager, IInterpreter $interpreter, DisplayManager $displayManager, KeyInputManager $keyInputManager) {
        $this->_processManager = $processManager;
        $this->_commandProcessor = $commandProcessor;
        $this->_memoryManager = $memoryManager;
        $this->_fileSystemManager = $fileSystemManager;
        $this->_displayManager = $displayManager;
        $this->_keyInputManager = $keyInputManager;
        $this->_interpreter = $interpreter;
    }

    public function setAsyncHandler(string $class) {
        $this->_async = $class;
    }

    public function boot() {
        $this->_memoryManager->setAsyncHandler($this->_async);
        $this->_processManager->setMemoryManager($this->_memoryManager);

        // Initialize kernel components
        $this->_processManager->initialize();
        $this->_fileSystemManager->initialize();
        $this->_displayManager->initialize();
        $this->_keyInputManager->initialize();
        $this->_memoryManager->initialize();
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

        if (! $command || ! $resource) {
            return $response ?? "Invalid command.";
        }

        $resourceObj = App::instance()->resolve($resource);

        if (! $resourceObj) {
            return "Resource not found.";
        }

        $process = $this->_processManager->createProcess($resourceObj, $command);
        $this->_memoryManager->register($process);

        return $response;
    }

    public function handleScript($request) {
        $this->display("Not Implemented");
    }

    private function runAsync( $task ){
        // Call the do method statically
        return $this->_async::do($task);
    }

    private function handleCommand($request) {
        // Dispatch request to appropriate service or process
        try {
            $response = $this->execute($request);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->display($response);
    }

    private function handleCommandAsync($request) {
        // Dispatch request to appropriate service or process
        $this->_async::do((function() use ($request) {
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

    public function run() {
        while (true) {
            $this->_displayManager->send("# > ");
            $input = $this->_keyInputManager->capture();
            $this->handleRequest($input);
            $this->_displayManager->send("\n");
        }
    }

    public function shutdown() {
        // Shutdown kernel components
    }
}
