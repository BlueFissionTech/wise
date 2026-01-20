<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Sys\Drivers\BufferDisplayDriver;
use BlueFission\Wise\Sys\Drivers\CompositeDisplayDriver;
use BlueFission\Wise\Sys\Drivers\StreamDisplayDriver;
use PHPUnit\Framework\TestCase;

final class CompositeDisplayDriverTest extends TestCase
{
    public function testCompositeDriverWritesToAllTargets(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wise-output-');
        if ($path === false) {
            $this->fail('Failed to create temp file for composite driver test.');
        }

        $buffer = new BufferDisplayDriver();
        $stream = new StreamDisplayDriver($path, false);
        $driver = new CompositeDisplayDriver([$buffer, $stream]);

        $driver->handle("first\n");
        $driver->handle("second\n");
        $driver->print();

        $this->assertSame(["first\n", "second\n"], $buffer->output());
        $this->assertSame("first\nsecond\n", file_get_contents($path));

        unlink($path);
    }
}
