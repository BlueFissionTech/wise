<?php

namespace BlueFission\Wise\Arc\Traits;

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
    
    public function currentDir() {
        return $this->_fileSystemManager->path();
    }
}