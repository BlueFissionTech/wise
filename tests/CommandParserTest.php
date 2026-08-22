<?php

namespace BlueFission\Tests;

use BlueFission\Services\Application as App;
use BlueFission\Wise\Cmd\CommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testProcessQuestionPrefersNamedResource(): void
    {
        $parser = new CommandParser();

        $command = $parser->processQuestion('what is the weather?');

        $this->assertNotNull($command);
        $this->assertSame('get', $command->verb);
        $this->assertSame(['weather'], $command->resources);
        $this->assertSame([], $command->args);
    }

    #[DataProvider('separatedResourceIdentifiers')]
    public function testSeparatedResourceIdentifiersNormalizeDeterministically(
        string $input,
        string $resource
    ): void {
        $parser = new CommandParser();

        $first = $parser->parse($input);
        $second = $parser->parse($input);

        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame([$resource], $first->resources);
        $this->assertSame([], $first->args);
    }

    public static function separatedResourceIdentifiers(): array
    {
        return [
            'hyphen' => ['list missing-resource', 'missing-resource'],
            'underscore alias' => ['list missing_resource', 'missing-resource'],
        ];
    }

    public function testSeparatedAliasResolvesRegisteredServiceName(): void
    {
        App::instance()->register('parser_test_resource', 'list', fn (): null => null);
        $parser = new CommandParser();

        $command = $parser->parse('list parser-test-resource');

        $this->assertSame(['parser_test_resource'], $command->resources);
        $this->assertSame([], $command->args);
    }
}
