<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cmd\Command;
use BlueFission\Wise\Cmd\CommandResult;
use PHPUnit\Framework\TestCase;

final class CommandResultTest extends TestCase
{
    public function testWithMetadataReturnsANewResultWithCallerKeysTakingPrecedence(): void
    {
        $result = CommandResult::completed('done', metadata: [
            'source' => 'resource',
            'correlation_id' => 'resource-correlation',
        ]);

        $augmented = $result->withMetadata([
            'correlation_id' => 'host-correlation',
            'tenant_id' => 'tenant-1',
        ]);

        $this->assertNotSame($result, $augmented);
        $this->assertSame([
            'source' => 'resource',
            'correlation_id' => 'resource-correlation',
        ], $result->metadata());
        $this->assertSame([
            'source' => 'resource',
            'correlation_id' => 'host-correlation',
            'tenant_id' => 'tenant-1',
        ], $augmented->metadata());
    }

    public function testWithMetadataPreservesEveryResultVariant(): void
    {
        $command = new Command();
        $command->verb = 'inspect';
        $command->resources = ['resource'];

        $results = [
            CommandResult::completed('done', $command, ['source' => 'completed']),
            CommandResult::parsed($command, ['source' => 'parsed']),
            CommandResult::pending('approve?', $command, 'resume-1', ['source' => 'pending']),
            CommandResult::invalid('invalid', ['invalid_command'], ['source' => 'invalid'], $command),
            CommandResult::failure('failed', ['handler_failed'], ['source' => 'failed']),
        ];

        foreach ($results as $result) {
            $augmented = $result->withMetadata(['host' => 'opus']);

            $this->assertNotSame($result, $augmented);
            $this->assertSame($result->status(), $augmented->status());
            $this->assertSame($result->output(), $augmented->output());
            $this->assertSame($result->command(), $augmented->command());
            $this->assertSame($result->description(), $augmented->description());
            $this->assertSame($result->confirmationRequired(), $augmented->confirmationRequired());
            $this->assertSame($result->exitCode(), $augmented->exitCode());
            $this->assertSame($result->diagnostics(), $augmented->diagnostics());
            $this->assertSame($result->continuationToken(), $augmented->continuationToken());
            $this->assertSame(
                [...$result->metadata(), 'host' => 'opus'],
                $augmented->metadata()
            );
        }
    }
}
