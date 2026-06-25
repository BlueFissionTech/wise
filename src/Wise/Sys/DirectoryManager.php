<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Data\Directory;
use BlueFission\Data\FileSystem;

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
}
