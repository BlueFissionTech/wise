<?php

namespace BlueFission\Tests;

use BlueFission\Arr;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Cmd\CommandHandler;
use BlueFission\Wise\Cmd\CommandRequest;
use BlueFission\Wise\Cmd\CommandResult;
use BlueFission\Wise\Cmd\CommandRuntime;
use BlueFission\Wise\Cmd\ICommandProcessor;
use BlueFission\Wise\Cmd\ICommandRuntime;
use BlueFission\Wise\Cmd\OutputFrame;
use BlueFission\Wise\Cmd\RuntimeContext;
use BlueFission\Wise\Exe\BridgeResult;
use BlueFission\Wise\Exe\ExecutionRequest;
use PHPUnit\Framework\TestCase;

final class CommandRuntimeTest extends TestCase
{
    public function testResourceExecutionPreservesIsolatedHostContextAndWritesNothing(): void
    {
        $processor = $this->processor();
        $runtime = new CommandRuntime($processor);

        ob_start();
        $first = $runtime->execute('inspect resource', new RuntimeContext([
            'correlation_id' => 'first',
        ], actor: ['id' => 'actor-1'], capabilities: ['read']));
        $second = $runtime->execute('inspect resource', new RuntimeContext([
            'correlation_id' => 'second',
        ], actor: ['id' => 'actor-2'], capabilities: ['write']));
        $terminalOutput = ob_get_clean();

        $this->assertSame('', $terminalOutput);
        $this->assertSame('first', $first->result()->metadata()['correlation_id']);
        $this->assertSame('actor-1', $first->result()->metadata()['actor']['id']);
        $this->assertSame('second', $second->result()->metadata()['correlation_id']);
        $this->assertSame('actor-2', $second->result()->metadata()['actor']['id']);
    }

    public function testCancellationAndDeadlineStopBeforeDispatch(): void
    {
        $processor = $this->processor();
        $runtime = new CommandRuntime($processor);

        $cancelled = $runtime->execute('inspect resource', new RuntimeContext(
            cancelCheck: fn (): bool => true
        ));
        $timedOut = $runtime->execute('inspect resource', new RuntimeContext(
            deadlineAt: microtime(true) - 1
        ));

        $this->assertSame(['execution_cancelled'], $cancelled->result()->diagnostics());
        $this->assertSame(['execution_timed_out'], $timedOut->result()->diagnostics());
        $this->assertSame(0, $processor->calls);
    }

    public function testNativeClearIsBlockedWithoutAnsiOutput(): void
    {
        $handler = new class extends CommandHandler {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function canHandle($command): bool
            {
                return true;
            }

            public function handle($command): string
            {
                $this->calls++;
                return "\033[2J";
            }
        };
        $runtime = new CommandRuntime($this->processor(), $handler);

        ob_start();
        $result = $runtime->execute('clear', new RuntimeContext());
        $terminalOutput = ob_get_clean();

        $this->assertSame('', $terminalOutput);
        $this->assertSame(CommandResult::FAILED, $result->result()->status());
        $this->assertSame(['headless_screen_control'], $result->result()->diagnostics());
        $this->assertSame(0, $handler->calls);
    }

    public function testScriptPolicyAndBridgeResultsAreStructured(): void
    {
        $kernel = new class(BridgeResult::success('script output', ['bridge' => 'test'])) extends Kernel {
            public ?ExecutionRequest $captured = null;

            public function __construct(private BridgeResult $nextResult)
            {
            }

            public function executeScript(ExecutionRequest $request): BridgeResult
            {
                $this->captured = $request;
                return $this->nextResult;
            }
        };
        $runtime = new CommandRuntime($this->processor(), kernel: $kernel);
        $denied = $runtime->executeScript('cmd/test.jss', new RuntimeContext(
            workingDirectory: 'workspace'
        ));
        $context = new RuntimeContext(
            ['correlation_id' => 'script-1'],
            ['one'],
            'workspace',
            ['PUBLIC_VALUE' => 'ok', 'SECRET_VALUE' => 'hidden'],
            ['PUBLIC_VALUE'],
            capabilities: [ExecutionRequest::CAP_FILESYSTEM, ExecutionRequest::CAP_ENVIRONMENT]
        );
        $completed = $runtime->executeScript('cmd/test.jss', $context, 'input');

        $this->assertSame(['capability_denied', ExecutionRequest::CAP_FILESYSTEM], $denied->result()->diagnostics());
        $this->assertSame(CommandResult::COMPLETED, $completed->result()->status());
        $this->assertSame('script output', $completed->result()->output());
        $this->assertSame('test', $completed->result()->metadata()['bridge']);
        $this->assertSame(['PUBLIC_VALUE' => 'ok'], $kernel->captured?->environment());
        $this->assertSame(['one'], $kernel->captured?->arguments());
        $this->assertSame('input', $kernel->captured?->standardInput());
    }

    public function testFramesAndDiscoveryExposeTypedHostContracts(): void
    {
        $handler = new class extends CommandHandler {
            public function __construct()
            {
            }

            public function availableCommands(): array
            {
                return ['echo', 'help'];
            }
        };
        $runtime = new CommandRuntime($this->processor(), $handler);
        $result = $runtime->execute('inspect resource', new RuntimeContext());
        $frameTypes = array_column($result->toArray()['frames'], 'type');

        $this->assertInstanceOf(ICommandRuntime::class, $runtime);
        $this->assertSame([OutputFrame::STATUS, OutputFrame::OUTPUT], $frameTypes);
        $this->assertSame([
            'commands' => ['inspect resource'],
            'native' => ['echo', 'help'],
            'script_extensions' => [],
        ], $runtime->discover());
    }

    public function testConfirmationContinuationIsPreservedInPromptFrame(): void
    {
        $processor = new class implements ICommandProcessor {
            public function process(CommandRequest|\BlueFission\Wise\Cmd\Command|array|string $request): CommandResult
            {
                $request = $request instanceof CommandRequest ? $request : new CommandRequest($request);

                return CommandResult::pending(
                    'Approve command?',
                    null,
                    'continuation-1',
                    $request->context()
                );
            }
        };
        $runtime = new CommandRuntime($processor);
        $result = $runtime->execute('inspect resource', new RuntimeContext([
            'correlation_id' => 'confirmation-1',
        ]));
        $frames = $result->toArray()['frames'];
        $prompt = Arr::make($frames)->pop();

        $this->assertTrue($result->result()->confirmationRequired());
        $this->assertSame('continuation-1', $result->result()->continuationToken());
        $this->assertSame(0, $result->result()->exitCode());
        $this->assertSame(OutputFrame::PROMPT, $prompt['type']);
        $this->assertSame('continuation-1', $prompt['metadata']['continuation_token']);
        $this->assertSame('confirmation-1', $result->result()->metadata()['correlation_id']);
    }

    private function processor(): ICommandProcessor
    {
        return new class implements ICommandProcessor {
            public int $calls = 0;

            public function process(CommandRequest|\BlueFission\Wise\Cmd\Command|array|string $request): CommandResult
            {
                $this->calls++;
                $request = $request instanceof CommandRequest ? $request : new CommandRequest($request);

                return CommandResult::completed('processed', metadata: $request->context());
            }

            public function availableCommands(): array
            {
                return ['inspect resource'];
            }
        };
    }
}
