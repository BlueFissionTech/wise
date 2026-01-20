<?php

namespace BlueFission\Tests\Sys\Config;

use BlueFission\Wise\Sys\Config\ConfigLocator;
use PHPUnit\Framework\TestCase;

final class ConfigLocatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-config-' . uniqid();
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testResolvePrefersUserOverride(): void
    {
        $systemPath = $this->seedConfig('cfg/net/default.jss', '#!jenss');
        $userPath = $this->seedConfig('usr/console/cfg/net/default.jss', '#!jenss');

        $locator = new ConfigLocator($this->root, 'console');

        $this->assertSame($userPath, $locator->resolve('net'));
        $this->assertNotSame($systemPath, $locator->resolve('net'));
    }

    public function testResolveFallsBackToSystemConfig(): void
    {
        $systemPath = $this->seedConfig('cfg/net/default.jss', '#!jenss');

        $locator = new ConfigLocator($this->root, 'console');

        $this->assertSame($systemPath, $locator->resolve('net'));
    }

    public function testOverlayPathsReturnsUserThenSystem(): void
    {
        $systemPath = $this->seedConfig('cfg/net/default.jss', '#!jenss');
        $userPath = $this->seedConfig('usr/console/cfg/net/default.jss', '#!jenss');

        $locator = new ConfigLocator($this->root, 'console');

        $this->assertSame([$userPath, $systemPath], $locator->overlayPaths('net'));
    }

    private function seedConfig(string $relativePath, string $content): string
    {
        $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
