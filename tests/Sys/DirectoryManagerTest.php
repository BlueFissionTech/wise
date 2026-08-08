<?php

namespace BlueFission\Tests\Sys;

use BlueFission\Wise\Sys\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class DirectoryManagerTest extends TestCase
{
    public function testEnsurePathCreatesNestedDirectory(): void
    {
        $path = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'artifacts'
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'directory-manager-test'
            . DIRECTORY_SEPARATOR . 'nested';

        $this->assertTrue(DirectoryManager::ensurePath($path));
        $this->assertTrue(DirectoryManager::pathExists($path));
    }

    public function testEnsurePathRejectsEmptyPath(): void
    {
        $this->assertFalse(DirectoryManager::ensurePath(''));
    }
}
