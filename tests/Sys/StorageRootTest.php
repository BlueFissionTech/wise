<?php

namespace BlueFission\Tests\Sys;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Wise\Sys\DirectoryManager;
use BlueFission\Wise\Sys\StorageRoot;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StorageRootTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-storage-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testPreparesConfiguredStoragePath(): void
    {
        $storage = new StorageRoot($this->root);
        $path = $storage->prepare('system');

        $this->assertSame($this->root . DIRECTORY_SEPARATOR . 'system', $path);
        $this->assertTrue(DirectoryManager::pathExists($path));
    }

    public function testDispatchesSuccessWhenPathIsPrepared(): void
    {
        $storage = new StorageRoot($this->root);
        $dispatched = false;
        $storage->when(Event::SUCCESS, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $storage->prepare('files');

        $this->assertTrue($dispatched);
    }

    public function testRejectsTraversalInRootAndSegments(): void
    {
        $this->expectException(RuntimeException::class);
        new StorageRoot($this->root . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'outside');
    }

    public function testRejectsNestedStorageSegment(): void
    {
        $storage = new StorageRoot($this->root);

        $this->expectException(RuntimeException::class);
        $storage->path('system/other');
    }

    public function testRejectsUnwritableStoragePath(): void
    {
        $storage = new class($this->root) extends StorageRoot {
            protected function isWritable(string $path): bool
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Storage path is unavailable');
        $storage->prepare('system');
    }

    private function removeDirectory(string $path): void
    {
        if (!DirectoryManager::pathExists($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (DirectoryManager::pathExists($target)) {
                $this->removeDirectory($target);
                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
