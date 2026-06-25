<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Sys\Drivers\StreamDisplayDriver;
use BlueFission\Wise\Sys\FileSystemManager;
use PHPUnit\Framework\TestCase;

final class StreamDisplayDriverTest extends TestCase
{
    public function testWritesToFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wise-output-');

        $driver = new StreamDisplayDriver($path, false);
        $driver->handle("first\n");
        $driver->print();
        $driver->send("second\n");

        $contents = FileSystemManager::readPath($path);

        $this->assertStringContainsString("first\n", $contents);
        $this->assertStringContainsString("second\n", $contents);

        unlink($path);
    }
}
