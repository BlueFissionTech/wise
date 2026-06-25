<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cmd\CommandParser;
use PHPUnit\Framework\TestCase;

final class CommandParserRoutingTest extends TestCase
{
    public function testPrepositionsSplitArgs(): void
    {
        $parser = new CommandParser();

        $command = $parser->parse('find file in documents');

        $this->assertSame('find', $command->verb);
        $this->assertSame(['file'], $command->resources);
        $this->assertSame(['documents'], $command->args);
    }

    public function testNoiseWordsAreIgnored(): void
    {
        $parser = new CommandParser();

        $command = $parser->parse('list the files');

        $this->assertSame('list', $command->verb);
        $this->assertSame(['file'], $command->resources);
        $this->assertSame([], $command->args);
    }

    public function testQuestionParserReturnsNullForNonQuestions(): void
    {
        $parser = new CommandParser();

        $command = $parser->processQuestion('list files');

        $this->assertNull($command);
    }

    public function testPluralResourceNormalization(): void
    {
        $parser = new CommandParser();

        $command = $parser->parse('list todos');

        $this->assertSame(['todo'], $command->resources);
    }
}
