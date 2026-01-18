<?php

namespace BlueFission\Tests\Cli;

use BlueFission\Wise\Cli\Components\TextOutput;
use PHPUnit\Framework\TestCase;

final class TextOutputTest extends TestCase
{
    public function testDrawUpdatesLineBuffer(): void
    {
        $output = new TextOutput(0, 0, 20, 5);
        $output->addLine('Hello');

        $output->draw();

        $this->assertSame('H', $output->getCharacterAtPosition(0, 0));
        $this->assertSame('e', $output->getCharacterAtPosition(1, 0));
    }
}
