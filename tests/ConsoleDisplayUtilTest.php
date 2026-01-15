<?php

namespace BlueFission\Tests;

use BlueFission\Connections\Stdio;
use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;
use PHPUnit\Framework\TestCase;

final class ConsoleDisplayUtilTest extends TestCase
{
    private function makeStdio(): Stdio
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'wise-stdin-');
        $outputPath = tempnam(sys_get_temp_dir(), 'wise-stdout-');

        $stdio = new Stdio([
            'target' => $inputPath,
            'output' => $outputPath,
        ]);

        return $stdio;
    }

    public function testUpdateReinitializesBuffersWithoutError(): void
    {
        $stdio = $this->makeStdio();
        ConsoleDisplayUtil::init($stdio);

        ConsoleDisplayUtil::display('hello');
        ConsoleDisplayUtil::update();

        $currentBuffer = new \ReflectionProperty(ConsoleDisplayUtil::class, '_currentBuffer');
        $currentBuffer->setAccessible(true);
        $newBuffer = new \ReflectionProperty(ConsoleDisplayUtil::class, '_newBuffer');
        $newBuffer->setAccessible(true);

        $this->assertNotEmpty($currentBuffer->getValue());
        $this->assertNotEmpty($newBuffer->getValue());
    }

    public function testParseAnsiCodesStripsCodesAndTracksPositions(): void
    {
        $input = "\033[31mhello\033[0m";
        $parsed = ConsoleDisplayUtil::parseAnsiCodes($input);

        $this->assertSame('hello', $parsed['content']);
        $this->assertArrayHasKey(0, $parsed['ansiCodes']);
    }
}
