<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Sys\IO\CommandInputStream;
use PHPUnit\Framework\TestCase;

final class CommandInputStreamTest extends TestCase
{
    public function testReadsLinesFromArray(): void
    {
        $stream = new CommandInputStream(['first', 'second']);

        $this->assertSame('first' . PHP_EOL, $stream->read());
        $this->assertSame('second' . PHP_EOL, $stream->read());
        $this->assertNull($stream->read());
    }

    public function testReadsLinesFromFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wise-input-');
        file_put_contents($path, "alpha\nbeta\n");

        $stream = CommandInputStream::fromFile($path);

        $this->assertSame('alpha' . PHP_EOL, $stream->read());
        $this->assertSame('beta' . PHP_EOL, $stream->read());
        $this->assertNull($stream->read());

        unlink($path);
    }
}
