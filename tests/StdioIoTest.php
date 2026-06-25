<?php

namespace BlueFission\Tests;

use BlueFission\Connections\Stdio;
use BlueFission\Wise\Sys\FileSystemManager;
use PHPUnit\Framework\TestCase;

final class StdioIoTest extends TestCase
{
    public function testSendWritesToOutputStream(): void
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'wise-stdin-');
        $outputPath = tempnam(sys_get_temp_dir(), 'wise-stdout-');

        $stdio = new Stdio([
            'target' => $inputPath,
            'output' => $outputPath,
        ]);
        $stdio->open();

        $stdio->send('hello');

        $written = FileSystemManager::readPath($outputPath);
        $this->assertSame('hello', $written);
    }

    public function testListenReadsFromInputStream(): void
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'wise-stdin-');
        $outputPath = tempnam(sys_get_temp_dir(), 'wise-stdout-');

        file_put_contents($inputPath, "ping\n");

        $stdio = new Stdio([
            'target' => $inputPath,
            'output' => $outputPath,
        ]);
        $stdio->open();

        $stdio->query();

        $this->assertSame("ping\n", $stdio->result());
    }
}
