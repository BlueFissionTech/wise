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
        $this->registerAlias('mem', 'memory');
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

    public function memory($scope = null, $size = null)
    {
        if (!$this->_kernel->hasWorkingMemory()) {
            return 'Working memory is not configured.';
        }

        $scope = $scope ? strtolower($scope) : null;
        $size = $size !== null ? strtolower((string)$size) : null;

        if ($scope === null || $scope === 'status') {
            return $this->memoryStatus();
        }

        if (is_numeric($scope)) {
            $size = $scope;
            $scope = 'user';
        } elseif ($scope === 'max') {
            $scope = 'user';
        }

        if (!in_array($scope, ['user', 'global'], true)) {
            return 'Usage: memory [user|global] [size|off]';
        }

        if ($scope === 'global' && !$this->canManageGlobalMemory()) {
            return 'Insufficient permissions to modify global memory.';
        }

        $parsedSize = $this->parseMemorySize($size);
        $ownerId = null;
        if ($scope === 'user') {
            $ownerId = $this->_kernel->profile()?->id();
        }

        $this->_kernel->setWorkingMemoryMaxSize($scope, $parsedSize, $ownerId);
        $current = $this->_kernel->workingMemoryMaxSize($scope, $ownerId);
        $label = $current !== null ? (string)$current : 'unlimited';

        return "Working memory {$scope} max size set to {$label}.";
    }

    public function exit() {
        exit;
    }

    // Add more internal commands as needed

    private function memoryStatus(): string
    {
        $userId = $this->_kernel->profile()?->id();
        $userSize = $this->_kernel->workingMemoryMaxSize('user', $userId);
        $globalSize = $this->_kernel->workingMemoryMaxSize('global');

        $userLabel = $userSize !== null ? (string)$userSize : 'unlimited';
        $globalLabel = $globalSize !== null ? (string)$globalSize : 'unlimited';

        return "Working memory max size (user: {$userLabel}, global: {$globalLabel}).";
    }

    private function canManageGlobalMemory(): bool
    {
        $profile = $this->_kernel->profile();
        if (!$profile) {
            return false;
        }

        return $profile->hasRole('admin') || $profile->hasRole('system');
    }

    private function parseMemorySize(?string $size): ?int
    {
        if ($size === null || $size === '' || $size === 'off' || $size === 'none') {
            return null;
        }

        if (!is_numeric($size)) {
            return null;
        }

        $parsed = (int)$size;
        return $parsed > 0 ? $parsed : null;
    }
}
