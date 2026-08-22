<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Exe\ExecutionRequest;

final class CommandRuntime implements ICommandRuntime
{
    public function __construct(
        private ICommandProcessor $processor,
        private ?CommandHandler $nativeHandler = null,
        private ?Kernel $kernel = null
    ) {
    }

    public function execute(
        CommandRequest|Command|array|string $request,
        RuntimeContext $context
    ): RuntimeResult {
        $guard = $this->guard($context);
        if ($guard instanceof RuntimeResult) {
            return $guard;
        }

        $request = $this->withContext($request, $context);
        $input = $request->input();
        if (Str::is($input) && $this->isScriptCommand((string)$input)) {
            $parts = Str::make((string)$input)->trim()->split();
            $parts->shift();
            $path = (string)$parts->shift();

            return $this->executeScript($path, $context);
        }

        if (Str::is($input) && $this->nativeHandler?->canHandle((string)$input)) {
            $commandName = Str::make((string)$input)->trim()->split()->shift();
            if (Str::match('clear', Str::lower((string)$commandName))) {
                return $this->failure(
                    'Screen control is unavailable in headless execution.',
                    ['headless_screen_control'],
                    $context
                );
            }

            try {
                $output = $this->nativeHandler->handle((string)$input);
                $result = $output instanceof CommandResult
                    ? $output->withMetadata($context->metadata())
                    : CommandResult::completed($output, metadata: $context->metadata());

                return RuntimeResult::fromCommandResult($result);
            } catch (\Throwable $exception) {
                return $this->failure(
                    'Native command execution failed.',
                    ['exception' => $exception::class],
                    $context
                );
            }
        }

        return RuntimeResult::fromCommandResult($this->processor->process($request));
    }

    public function executeScript(
        string $path,
        RuntimeContext $context,
        string $standardInput = ''
    ): RuntimeResult {
        $guard = $this->guard($context);
        if ($guard instanceof RuntimeResult) {
            return $guard;
        }
        if ($context->workingDirectory() !== null
            && !$context->hasCapability(ExecutionRequest::CAP_FILESYSTEM)) {
            return $this->failure(
                'Filesystem capability is required for a working directory.',
                ['capability_denied', ExecutionRequest::CAP_FILESYSTEM],
                $context
            );
        }
        if ($context->hasEnvironment()
            && !$context->hasCapability(ExecutionRequest::CAP_ENVIRONMENT)) {
            return $this->failure(
                'Environment capability is required for environment values.',
                ['capability_denied', ExecutionRequest::CAP_ENVIRONMENT],
                $context
            );
        }
        if (!$this->kernel instanceof Kernel) {
            return $this->failure('Script execution is unavailable.', ['script_runtime_unavailable'], $context);
        }

        try {
            $bridgeResult = $this->kernel->executeScript(
                $context->executionRequest($path, $standardInput)
            );
        } catch (\Throwable $exception) {
            return $this->failure(
                'Script execution failed.',
                ['exception' => $exception::class],
                $context
            );
        }

        $metadata = Arr::merge($context->metadata(), $bridgeResult->meta());
        $result = $bridgeResult->successFlag()
            ? CommandResult::completed($bridgeResult->output(), metadata: $metadata)
            : CommandResult::failure(
                $bridgeResult->output(),
                ['script_execution_failed'],
                $metadata
            );

        return RuntimeResult::fromCommandResult($result);
    }

    public function discover(): array
    {
        $resourceCommands = Func::isCallable([$this->processor, 'availableCommands'])
            ? $this->processor->availableCommands()
            : [];
        $nativeCommands = $this->nativeHandler?->availableCommands() ?? [];
        $scriptExtensions = $this->kernel?->bridgeRegistry()?->extensions() ?? [];

        return [
            'commands' => Arr::make($resourceCommands)->unique()->sort()->toArray(),
            'native' => Arr::make($nativeCommands)->unique()->sort()->toArray(),
            'script_extensions' => Arr::make($scriptExtensions)->unique()->sort()->toArray(),
        ];
    }

    private function withContext(
        CommandRequest|Command|array|string $request,
        RuntimeContext $context
    ): CommandRequest {
        if (!$request instanceof CommandRequest) {
            return new CommandRequest($request, context: $context->metadata());
        }

        $metadata = Arr::merge($context->metadata(), $request->context());
        if ($request->isContinuation()) {
            return CommandRequest::resume(
                (string)$request->continuationToken(),
                (bool)$request->approved(),
                $metadata
            );
        }

        return new CommandRequest($request->input(), $request->mode(), $metadata);
    }

    private function guard(RuntimeContext $context): ?RuntimeResult
    {
        if ($context->cancelled()) {
            return $this->failure('Command execution was cancelled.', ['execution_cancelled'], $context);
        }
        if ($context->timedOut()) {
            return $this->failure('Command execution timed out.', ['execution_timed_out'], $context);
        }

        return null;
    }

    private function failure(string $message, array $diagnostics, RuntimeContext $context): RuntimeResult
    {
        return RuntimeResult::fromCommandResult(
            CommandResult::failure($message, $diagnostics, $context->metadata())
        );
    }

    private function isScriptCommand(string $input): bool
    {
        $command = Str::make($input)->trim()->split()->shift();

        return Str::match('run', Str::lower((string)$command));
    }
}
