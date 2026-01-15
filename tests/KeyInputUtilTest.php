<?php

namespace BlueFission\Tests;

use BlueFission\Connections\Stdio;
use BlueFission\Wise\Sys\Utl\KeyInputUtil;
use PHPUnit\Framework\TestCase;

final class KeyInputUtilTest extends TestCase
{
    public function testInitStoresStdioInstance(): void
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'wise-stdin-');
        $outputPath = tempnam(sys_get_temp_dir(), 'wise-stdout-');

        $stdio = new Stdio([
            'target' => $inputPath,
            'output' => $outputPath,
        ]);
        $stdio->open();

        KeyInputUtil::init($stdio);

        $stored = new \ReflectionProperty(KeyInputUtil::class, '_stdio');
        $stored->setAccessible(true);

        $this->assertSame($stdio, $stored->getValue());
    }

    public function testListenReadsFromStdio(): void
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'wise-stdin-');
        $outputPath = tempnam(sys_get_temp_dir(), 'wise-stdout-');

        file_put_contents($inputPath, "data\n");

        $stdio = new Stdio([
            'target' => $inputPath,
            'output' => $outputPath,
        ]);
        $stdio->open();

        KeyInputUtil::init($stdio);

        $this->assertSame("data\n", KeyInputUtil::listen());
    }
}
