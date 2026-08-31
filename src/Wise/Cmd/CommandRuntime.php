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
            $identifier = CommandPolicy::native((string)$commandName);
            $denied = $this->denyUnlessAllowed($context, $identifier);
            if ($denied instanceof RuntimeResult) {
                return $denied;
            }
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

        $identifier = $this->resourceIdentifier($request->input());
        $denied = $this->denyUnlessAllowed($context, $identifier);
        if ($denied instanceof RuntimeResult) {
            return $denied;
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
        $denied = $this->denyUnlessAllowed($context, $this->scriptIdentifier($path));
        if ($denied instanceof RuntimeResult) {
            return $denied;
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

    public function discover(?RuntimeContext $context = null): array
    {
        $resourceCommands = Func::isCallable([$this->processor, 'availableCommands'])
            ? $this->processor->availableCommands()
            : [];
        $nativeCommands = $this->nativeHandler?->availableCommands() ?? [];
        $scriptExtensions = $this->kernel?->bridgeRegistry()?->extensions() ?? [];

        $resourceCommands = Arr::make($resourceCommands)
            ->filter(fn ($command) => $this->allowed($context, $this->resourceIdentifier((string)$command)))
            ->unique()
            ->sort()
            ->toArray();
        $nativeCommands = Arr::make($nativeCommands)
            ->filter(fn ($command) => $this->allowed($context, CommandPolicy::native((string)$command)))
            ->unique()
            ->sort()
            ->toArray();
        $scriptExtensions = Arr::make($scriptExtensions)
            ->filter(fn ($extension) => $this->allowed($context, CommandPolicy::script((string)$extension)))
            ->unique()
            ->sort()
            ->toArray();

        return [
            'commands' => $resourceCommands,
            'native' => $nativeCommands,
            'script_extensions' => $scriptExtensions,
        ];
    }

    public function descriptors(?RuntimeContext $context = null): array
    {
        $discovery = $this->discover($context);
        $descriptors = Arr::make();
        foreach ($discovery['commands'] as $command) {
            $descriptors->push(CommandDescriptor::resource((string)$command)->toArray());
        }
        foreach ($discovery['native'] as $command) {
            $descriptors->push(CommandDescriptor::native((string)$command)->toArray());
        }
        foreach ($discovery['script_extensions'] as $extension) {
            $descriptors->push(CommandDescriptor::script((string)$extension)->toArray());
        }

        return $descriptors
            ->sort(fn (array $left, array $right): int => $left['identifier'] <=> $right['identifier'])
            ->toArray();
    }

    public function descriptor(string $identifier, ?RuntimeContext $context = null): CommandDescriptor
    {
        $identifier = Str::make($identifier)->trim()->lower()->val();
        foreach ($this->descriptors($context) as $descriptor) {
            if (Str::match($identifier, (string)$descriptor['identifier'])) {
                return new CommandDescriptor(
                    $descriptor['identifier'],
                    $descriptor['route'],
                    $descriptor['resource'],
                    $descriptor['action'],
                    $descriptor['summary'],
                    $descriptor['argument_shape'],
                    $descriptor['confirmation_required'],
                    $descriptor['required_capabilities'],
                    $descriptor['available'],
                    $descriptor['unavailable_reason']
                );
            }
        }

        return CommandDescriptor::unavailable($identifier);
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

    private function allowed(?RuntimeContext $context, string $identifier): bool
    {
        return !$context instanceof RuntimeContext || $context->allowsCommand($identifier);
    }

    private function denyUnlessAllowed(RuntimeContext $context, string $identifier): ?RuntimeResult
    {
        if ($context->allowsCommand($identifier)) {
            return null;
        }

        return $this->failure(
            'Command is not allowed by the request policy.',
            ['command_policy_denied', 'identifier' => $identifier],
            $context
        );
    }

    private function resourceIdentifier(Command|array|string $input): string
    {
        if ($input instanceof Command) {
            return CommandPolicy::resource(
                (string)(Arr::make($input->resources)->shift() ?? ''),
                (string)$input->verb
            );
        }
        if (Arr::is($input)) {
            $resource = $input['resource'] ?? $input['objects'] ?? $input['resources'] ?? '';
            if (Arr::is($resource)) {
                $resource = Arr::make($resource)->shift();
            }
            $action = $input['verb'] ?? $input['operator'] ?? '';

            return CommandPolicy::resource((string)$resource, (string)$action);
        }

        $parts = Str::make((string)$input)->trim()->split();
        $action = (string)$parts->shift();
        $resource = (string)$parts->shift();

        return CommandPolicy::resource($resource, $action);
    }

    private function scriptIdentifier(string $path): string
    {
        $parts = Str::make($path)->split('.');
        $extension = (string)$parts->pop();

        return CommandPolicy::script($extension);
    }
}
