<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Data\FileSystem;

class CommandHandler {
    protected $_aliases = [];
    protected $_kernel;

    public function __construct(Kernel $kernel) {
        $this->_kernel = $kernel;

        // Register internal commands
        $this->registerInternalCommands();
    }

    public function canHandle($command) {
        // Check if the command is a native command or an alias
        $commandName = explode(' ', $command)[0];
        return isset($this->_aliases[$commandName]) || method_exists($this, $commandName);
    }

    public function handle($command) {
        // Parse the command and execute the corresponding method
        
        $parts = explode(' ', $command);
        $commandName = array_shift($parts);
        $args = $parts;

        if (isset($this->_aliases[$commandName])) {
            $commandName = $this->_aliases[$commandName];
        }

        if (method_exists($this, $commandName)) {
            return call_user_func_array([$this, $commandName], $args);
        } else {
            throw new \Exception("Command not found: $commandName");
        }
    }

    public function registerAlias($alias, $command) {
        $this->_aliases[$alias] = $command;
    }

    protected function registerInternalCommands() {
        // Register aliases for internal commands
        $this->registerAlias('list', 'listDir');
        $this->registerAlias('go', 'changeDir');
        $this->registerAlias('del', 'deleteFile');
        $this->registerAlias('rm', 'deleteFile');
        $this->registerAlias('show', 'readFile');
        $this->registerAlias('create', 'createFile');
        $this->registerAlias('write', 'writeFile');
        $this->registerAlias('move', 'moveFile');
        $this->registerAlias('copy', 'copyFile');
        $this->registerAlias('mkdir', 'createDir');
        $this->registerAlias('help', 'help');
        $this->registerAlias('echo', 'echo');
        $this->registerAlias('clear', 'clearScreen');
        $this->registerAlias('exit', 'exit');
        // Add more aliases as needed
    }

    // Define internal commands
    public function listDir($dir = null) {
        return $this->_kernel->listDir($dir);
    }

    public function changeDir($path) {
        return $this->_kernel->changeDir($path);
    }

    public function createDir($dir) {
        return $this->_kernel->createDir($dir);
    }

    public function createFile($file) {
        return $this->_kernel->createFile($file);
    }

    public function writeFile($file, $contents) {
        return $this->_kernel->writeFile($file, $contents);
    }

    public function deleteFile($file) {
        return $this->_kernel->deleteFile($file);
    }

    public function readFile($file) {
        return $this->_kernel->readFile($file);
    }

    public function moveFile($destination, $file = null) {
        return $this->_kernel->moveFile($destination, $file);
    }

    public function copyFile($destination, $file = null) {
        return $this->_kernel->copyFile($destination, $file);
    }

    public function echo($message) {
        return $message;
    }

    public function help() {
        return "Available commands: list (ls), changeDirectory (cd), delete (rm), view (cat), echo";
    }

    public function clearScreen() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return "\e[H\e[J";
        } else {
            return "\033[2J\033[;H";
        }

        return '';
    }

    public function exit() {
        exit;
    }

    // Add more internal commands as needed
}
