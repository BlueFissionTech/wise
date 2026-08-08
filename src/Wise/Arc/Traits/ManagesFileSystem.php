<?php

namespace BlueFission\Wise\Arc\Traits;

use BlueFission\Arr;
use BlueFission\Val;

trait ManagesFileSystem {
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
        if (Val::isNotEmpty($file)) {
            $this->_fileSystemManager->open($file);
        }
        $this->_fileSystemManager->move($destination);
        return $this->_fileSystemManager->status();
    }

    public function copyFile($destination, $file = null) {
        if (Val::isNotEmpty($file)) {
            $this->_fileSystemManager->open($file);
        }
        $this->_fileSystemManager->copy($destination);
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
        if (Val::isNotEmpty($dir)) {
            $this->_fileSystemManager->open($dir);
        }

        $list = $this->_fileSystemManager->listDir();

        if (Val::isEmpty($list) || (Arr::is($list) && Arr::isEmpty($list))) {
            return $this->_fileSystemManager->status();
        }

        return Arr::make($list)->join(PHP_EOL)->val() . PHP_EOL;
    }
    
    public function currentDir() {
        return $this->_fileSystemManager->path();
    }
}
