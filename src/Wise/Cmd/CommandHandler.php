<?php

namespace BlueFission\Wise\Kernel;

use BlueFission\Data\FileSystem;

class CommandHandler {
    protected $aliases = [];

    public function __construct() {
        // Register internal commands
        $this->registerInternalCommands();
    }

    public function canHandle($command) {
        // Check if the command is a native command or an alias
        $commandName = explode(' ', $command)[0];
        return isset($this->aliases[$commandName]) || method_exists($this, $commandName);
    }

    public function handle($command) {
        // Parse the command and execute the corresponding method
        $parts = explode(' ', $command);
        $commandName = array_shift($parts);
        $args = $parts;

        if (isset($this->aliases[$commandName])) {
            $commandName = $this->aliases[$commandName];
        }

        if (method_exists($this, $commandName)) {
            return call_user_func_array([$this, $commandName], $args);
        } else {
            throw new \Exception("Command not found: $commandName");
        }
    }

    public function registerAlias($alias, $command) {
        $this->aliases[$alias] = $command;
    }

    protected function registerInternalCommands() {
        // Register aliases for internal commands
        $this->registerAlias('ls', 'list');
        $this->registerAlias('dir', 'list');
        $this->registerAlias('cd', 'changeDirectory');
        $this->registerAlias('rm', 'delete');
        $this->registerAlias('cat', 'view');
        // Add more aliases as needed
    }

    // Define internal commands
    public function list() {
        $fileSystem = new FileSystem(['root' => getcwd()]);
        return $fileSystem->listDir();
    }

    public function changeDirectory($path) {
        if (chdir($path)) {
            return "Directory changed to " . getcwd();
        } else {
            return "Failed to change directory.";
        }
    }

    public function delete($file) {
        $fileSystem = new FileSystem();
        $fileSystem->open($file);
        $fileSystem->delete(true);
        return $fileSystem->status();
    }

    public function view($file) {
        $fileSystem = new FileSystem();
        $fileSystem->open($file);
        $fileSystem->read();
        $content = $fileSystem->contents();
        $fileSystem->close();
        return $content ?? $fileSystem->status();
    }

    public function echo($message) {
        return $message;
    }

    public function help() {
        return "Available commands: list (ls), changeDirectory (cd), delete (rm), view (cat), echo";
    }

    // Add more internal commands as needed
}
