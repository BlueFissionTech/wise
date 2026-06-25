<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Data\FileSystem;
use BlueFission\Str;

class FileSystemManager extends FileSystem {
    public function initialize() {
        // Initialize file system manager
    }

    public static function pathExists(?string $path): bool
    {
        $path = $path ? (realpath($path) ?: $path) : $path;

        return FileSystem::fileExists($path);
    }

    public static function readPath(string $path, string $default = ''): string
    {
        $path = realpath($path) ?: $path;

        if (!self::pathExists($path)) {
            return $default;
        }

        $reader = new static(['root' => dirname($path), 'filter' => []]);
        $reader->open(basename($path));
        $reader->read();
        $contents = $reader->contents();
        $reader->close();

        return Str::is($contents) ? $contents : $default;
    }
}
