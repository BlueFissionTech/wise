<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\{
    MemoryManager,
    FileSystemManager,
    DisplayManager,
    KeyInputManager
};
use BlueFission\Wise\Usr\Identity;
use BlueFission\Wise\Cmd\CommandHandler;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\Async\Async;
use BlueFission\Data\Storage\Storage;
use BlueFission\Services\Application as App;
use BlueFission\Services\Authenticator as Auth;

class Kernel {
    protected $_identity;
    protected $_processManager;
    protected $_commandProcessor;
    protected $_commandHandler;
    protected $_memoryManager;
    protected $_fileSystemManager;
    protected $_displayManager;
    protected $_keyInputManager;
    protected $_sessionStorage;
    protected $_dataStorage;
    protected $_interpreter;
    protected $_async;
    protected $_base;

    protected static $_instance = null;

    public function __construct(ProcessManager $processManager, CommandProcessor $commandProcessor, MemoryManager $memoryManager, FileSystemManager $fileSystemManager, IInterpreter $interpreter, DisplayManager $displayManager, KeyInputManager $keyInputManager, Storage $sessionStorage, Storage $dataStorage) {
        if (self::$_instance) {
            return self::$_instance;
        }

        $this->_processManager = $processManager;
        $this->_commandProcessor = $commandProcessor;
        $this->_memoryManager = $memoryManager;
        $this->_fileSystemManager = $fileSystemManager;
        $this->_displayManager = $displayManager;
        $this->_keyInputManager = $keyInputManager;
        $this->_interpreter = $interpreter;
        $this->_sessionStorage = $sessionStorage;
        $this->_dataStorage = $dataStorage;

        $this->_commandHandler = new CommandHandler($this);
        $this->_identity = new Identity($this, new Auth( $this->_sessionStorage, $this->_dataStorage ));
        $this->_base = new Base($this);

        // Set STDIN to non-blocking mode
        self::$_instance = $this;
    }

    public static function instance() {
        return self::$_instance;
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

        $this->_fileSystemManager->open();
    }

    public function handle($request)
    {
        $request = trim($request);
        // this method shoud determine if a request should be handled by the shell interpreter, the command processor, or directly by the kernel's internal commands

        // Determine if request is a command or a script
        if ($this->_commandHandler->canHandle($request)) {
            $this->handleNative($request);
        } elseif ($this->_interpreter->isValid($request)) {
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

    public function runAsync( $task ){
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
        $this->display($response, ['color'=>'yellow']);
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

    private function handleNative($request) {
        $output = $this->_commandHandler->handle($request);
        $this->display($output, ['color'=>'yellow']);
    }

    public function display($response, $args = []) {
        $this->_displayManager->send($response, $args);
    }

    public function displayLine($response, $args = []) {
        $this->display($response . PHP_EOL, $args);
    }

    public function input() {
        return $this->_keyInputManager->capture();
    }

    public function prompt($prompt = null) {
        if ( $prompt != null ) {
            $prompt = " {$prompt} ";
        } else {
            $dir = $this->currentDir();
            $prompt = " \033[90m{$dir}\033[0m ";
        }

        $this->display("#{$prompt}> ");
    }

    public function inputSilent( $prompt = "" ) {
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
              $vbscript, 'wscript.echo(InputBox("'
              . addslashes($prompt)
              . '", "", "password here"))');
            $command = "cscript //nologo " . escapeshellarg($vbscript);
            $password = rtrim(shell_exec($command));
            unlink($vbscript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
                trigger_error("Can't invoke bash");
                return;
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
              . addslashes($prompt)
              . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    public function run() {
        $this->clear();

        $notification_interval = 5;
        $start_time = time();

        $this->_async::do(function() use ($start_time, $notification_interval) {
            while (true) {
                // Check if it's time to show a notification
                if (time() - $start_time >= $notification_interval) {
                    $this->display("Notification: " . date('H:i:s') . "\n");
                    return;
                }

                sleep(1); // 0.1 seconds
            }
        })->then((function($response) {

        })->bindTo($this), function($error) {
            // Handle this somehow
        })->try();

        while (true) {
            $this->update();
            // $this->clearScreen();

            $this->splash();
            // $this->maybeLogin();


            if ( $input = $this->input() ) {
                $this->handle($input);
                $this->display("\n");
                $this->prompt();
            }
            $this->draw();

            usleep(100000); // Sleep for 0.1 second
        }
    }

    public function shutdown() {
        // Shutdown kernel components
    }

    private function splash() {
        $this->_base->splash();
    }

    private function welcome() {

    }

    private function maybeLogin() {
        // Check if user is logged in
        if ($this->_identity->isAuthenticated()) {
            return;
        }

        // If not, prompt for login
        $this->_identity->prompt();

        // temporarily allow logins
        return;

        if (!$this->_identity->isAuthenticated()) {
            exit(0);
        }
    }

    public function deleteFile($file) {
        $this->_fileSystemManager->open($file);
        $this->_fileSystemManager->delete(true);
        return $this->_fileSystemManager->status();
    }

    public function createFile($file) {
        $this->_fileSystemManager->open($file);
        $this->_fileSystemManager->write();
        return $this->_fileSystemManager->status();
    }

    public function writeFile($file, $contents) {
        $this->_fileSystemManager->open($file);
        $this->_fileSystemManager->contents($contents);
        $this->_fileSystemManager->write();
        return $this->_fileSystemManager->status();
    }

    public function readFile($file) {
        $this->_fileSystemManager->open($file);
        $this->_fileSystemManager->read();
        return $this->_fileSystemManager->contents() ?? $this->_fileSystemManager->status();
    }

    public function moveFile($destination, $file = null) {
        if ($file) {
            $this->_fileSystemManager->open($file);
        }
        $this->_fileSystemManager->move($new);
        return $this->_fileSystemManager->status();
    }

    public function copyFile($destination, $file = null) {
        if ($file) {
            $this->_fileSystemManager->open($file);
        }
        $this->_fileSystemManager->copy($new);
        return $this->_fileSystemManager->status();
    }

    public function changeDir($dir) {
        $this->_fileSystemManager->open($dir);
        return $this->_fileSystemManager->status();
    }

    public function createDir($dir) {
        $this->_fileSystemManager->open($dir);
        $this->_fileSystemManager->createDir();
        return $this->_fileSystemManager->status();
    }

    public function listDir($dir = null) {
        if ($dir) {
            $this->_fileSystemManager->open($dir);
        }

        $list = $this->_fileSystemManager->listDir();

        if (! $list || ( is_array($list) && count($list) == 0 )) {
            return $this->_fileSystemManager->status();
        }

        $output = '';
        foreach ($list as $item) {
            $output .= $item . PHP_EOL;
        }

        return $output;
    }

    public function update() {
        $this->_displayManager->update();
    }

    public function draw() {
        $this->_displayManager->draw();
    }

    public function clear() {
        $this->_displayManager->clear();
    }

    public function clearScreen() {
        $this->_displayManager->clearScreen();
    }

    public function currentDir() {
        return $this->_fileSystemManager->path();
    }
}
