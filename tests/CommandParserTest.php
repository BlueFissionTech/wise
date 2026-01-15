<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cmd\CommandParser;
use PHPUnit\Framework\TestCase;

final class CommandParserTest extends TestCase
{
    public function testParseVerbAndResource(): void
    {
        $parser = new CommandParser();

        $command = $parser->parse('list file');

        $this->assertSame('list', $command->verb);
        $this->assertSame(['file'], $command->resources);
        $this->assertSame([], $command->args);
    }

    public function testProcessPronounsReplacesContextTokens(): void
    {
        $parser = new CommandParser();
        $context = [
            'last_resource' => 'file',
            'last_set' => 'settings',
            'app' => 'wise',
        ];

        $resolved = $parser->processPronouns('list it', $context);

        $this->assertSame('list file', $resolved);
    }

    public function testParseQuotedArguments(): void
    {
        $parser = new CommandParser();

        $command = $parser->parse('list file "my report"');

        $this->assertSame('list', $command->verb);
        $this->assertSame(['file'], $command->resources);
        $this->assertSame(['my report'], $command->args);
    }

    public function testProcessQuestionReturnsSearchCommand(): void
    {
        $parser = new CommandParser();

        $command = $parser->processQuestion('what time');

        $this->assertNotNull($command);
        $this->assertSame('search', $command->verb);
        $this->assertSame(['model'], $command->resources);
        $this->assertSame(['time'], $command->args);
    }
}
