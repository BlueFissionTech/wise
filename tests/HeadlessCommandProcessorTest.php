<?php

namespace BlueFission\Tests;

use BlueFission\Data\Storage\Storage;
use BlueFission\Services\Application as App;
use BlueFission\Wise\Cmd\Command;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Cmd\CommandRequest;
use BlueFission\Wise\Cmd\CommandResult;
use PHPUnit\Framework\TestCase;

final class HeadlessCommandProcessorTest extends TestCase
{
    public function testEmptyInputReturnsTypedInvalidResult(): void
    {
        $result = $this->processor()->process('  ');

        $this->assertSame(CommandResult::INVALID, $result->status());
        $this->assertFalse($result->successful());
        $this->assertSame(2, $result->exitCode());
        $this->assertSame(['input_empty'], $result->diagnostics());
    }

    public function testTextCanBeParsedWithoutExecution(): void
    {
        $result = $this->processor()->process(CommandRequest::parse(
            'list file',
            ['request_id' => 'req-001']
        ));

        $this->assertSame(CommandResult::PARSED, $result->status());
        $this->assertTrue($result->successful());
        $this->assertSame('list', $result->command()?->verb);
        $this->assertSame(['file'], $result->command()?->resources);
        $this->assertSame('list file', $result->description());
        $this->assertSame(['request_id' => 'req-001'], $result->metadata());
    }

    public function testStructuredInputUsesInterpreterFieldAliases(): void
    {
        $result = $this->processor()->process(CommandRequest::parse([
            'operator' => 'list',
            'objects' => ['file'],
            'values' => ['alpha', 'alpha'],
        ]));

        $this->assertSame(CommandResult::PARSED, $result->status());
        $this->assertSame('list file alpha alpha', $result->description());
        $this->assertSame([
            'verb' => 'list',
            'resources' => ['file'],
            'args' => ['alpha', 'alpha'],
        ], $result->command()?->toArray());
    }

    public function testStructuredInputExecutesWithoutWritingToTerminal(): void
    {
        App::instance()->register('headless-resource', 'inspect', fn (): string => 'headless-output');
        $processor = $this->processor();

        ob_start();
        $result = $processor->process([
            'verb' => 'inspect',
            'resource' => 'headless-resource',
        ]);
        $terminalOutput = ob_get_clean();

        $this->assertSame('', $terminalOutput);
        $this->assertSame(CommandResult::COMPLETED, $result->status());
        $this->assertSame('Command triggered with empty response. Perhaps it failed?', $result->output());
        $this->assertSame(0, $result->exitCode());
        $this->assertSame('inspect headless-resource', $result->getDescription());
        $this->assertSame('headless-resource', $processor->lastResource());
    }

    public function testUnknownStructuredResourceReturnsInvalidResult(): void
    {
        $result = $this->processor()->process([
            'operator' => 'inspect',
            'objects' => ['unregistered-resource'],
        ]);

        $this->assertSame(CommandResult::INVALID, $result->status());
        $this->assertSame('Resource not found.', $result->output());
        $this->assertSame(['resource_not_found'], $result->diagnostics());
        $this->assertSame('inspect unregistered-resource', $result->description());
    }

    public function testResultsDoNotReuseCommandMetadataAcrossRequests(): void
    {
        $processor = $this->processor();

        $processor->process(CommandRequest::parse('list file'));
        $result = $processor->process('help');

        $this->assertSame(CommandResult::COMPLETED, $result->status());
        $this->assertNull($result->command());
        $this->assertSame('', $result->description());
    }

    public function testRepeatedCommandReturnsConfirmationState(): void
    {
        $processor = $this->processor();

        $processor->process('list file');
        $processor->process('list file');
        $processor->process('list file');
        $result = $processor->process('list file');

        $this->assertSame(CommandResult::CONFIRMATION_REQUIRED, $result->status());
        $this->assertTrue($result->confirmationRequired());
        $this->assertSame('list file', $result->description());
    }

    public function testUnhandledFailuresReturnSanitizedResult(): void
    {
        $processor = new class($this->makeStorage()) extends CommandProcessor {
            protected function executeCommand(Command $command)
            {
                throw new \RuntimeException('sensitive implementation detail');
            }
        };

        $result = $processor->process([
            'verb' => 'inspect',
            'resource' => 'headless-resource',
        ]);

        $this->assertSame(CommandResult::FAILED, $result->status());
        $this->assertSame('Command processing failed.', $result->output());
        $this->assertSame(['exception' => \RuntimeException::class], $result->diagnostics());
        $this->assertStringNotContainsString('sensitive', (string)$result->output());
    }

    public function testResultSerializesAsStableHostEnvelope(): void
    {
        $command = new Command();
        $command->verb = 'list';
        $command->resources = ['file'];

        $result = CommandResult::parsed($command, ['request_id' => 'req-002']);

        $this->assertSame([
            'status' => CommandResult::PARSED,
            'output' => null,
            'command' => [
                'verb' => 'list',
                'resources' => ['file'],
                'args' => [],
            ],
            'description' => 'list file',
            'confirmation_required' => false,
            'exit_code' => 0,
            'diagnostics' => [],
            'metadata' => ['request_id' => 'req-002'],
        ], $result->toArray());
    }

    private function processor(): CommandProcessor
    {
        return new CommandProcessor($this->makeStorage());
    }

    private function makeStorage(): Storage
    {
        $storage = new Storage();
        $source = new \ReflectionProperty(Storage::class, '_source');
        $source->setValue($storage, []);
        $storage->activate();

        return $storage;
    }
}
