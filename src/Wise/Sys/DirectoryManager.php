<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Data\Directory;
use BlueFission\Data\FileSystem;
use BlueFission\Str;

class DirectoryManager extends Directory
{
    public function __construct(?FileSystem $storage = null)
    {
        parent::__construct($storage ?? new FileSystem(['filter' => []]));
    }

    public static function pathExists(?string $path): bool
    {
        return (new static())->exists($path);
    }

    public static function pathIsReachable(?string $path): bool
    {
        return (new static())->isReachable($path);
    }

    public static function ensurePath(?string $path): bool
    {
        $path = Str::make((string)$path)->trim()->val();

        if (Str::isEmpty($path)) {
            return false;
        }

        if (self::pathExists($path)) {
            return true;
        }

        $parent = dirname($path);
        if ($parent !== $path && $parent !== '.' && !self::pathExists($parent)) {
            self::ensurePath($parent);
        }

        $storage = new FileSystem(['root' => $parent, 'filter' => []]);
        $storage->mkdir(basename($path));
        $storage->close();

        return self::pathExists($path);
    }
}
