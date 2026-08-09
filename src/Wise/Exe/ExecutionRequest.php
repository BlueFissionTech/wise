<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class ExecutionRequest extends Obj
{
    public const CAP_ENVIRONMENT = 'environment';
    public const CAP_FILESYSTEM = 'filesystem';
    public const CAP_NETWORK = 'network';
    public const CAP_PROCESS = 'process';
    public const CAP_PROVIDER = 'provider';

    private Str $path;
    private Arr $arguments;
    private ?Str $workingDirectory;
    private Str $standardInput;
    private Arr $environment;
    private Arr $capabilities;
    private Num $timeoutMs;
    private $cancelCheck;
    private float $startedAt;

    public function __construct(
        string $path,
        array $arguments = [],
        ?string $workingDirectory = null,
        string $standardInput = '',
        array $environment = [],
        array $capabilities = [],
        int $timeoutMs = 0,
        ?callable $cancelCheck = null
    ) {
        parent::__construct();
        $this->path = Str::make($path)->trim();
        $this->arguments = Arr::make($arguments);
        $this->workingDirectory = Val::isNotEmpty($workingDirectory)
            ? Str::make((string)$workingDirectory)->trim()
            : null;
        $this->standardInput = Str::make($standardInput);
        $this->environment = Arr::make($environment);
        $this->capabilities = Arr::make($capabilities)
            ->map(fn($capability) => Str::make((string)$capability)->trim()->lower()->val())
            ->unique();
        $this->timeoutMs = Num::make(Num::max(0, $timeoutMs));
        $this->cancelCheck = $cancelCheck;
        $this->startedAt = microtime(true);
    }

    public function path(): string
    {
        return $this->path->val();
    }

    public function withPath(string $path): self
    {
        $copy = clone $this;
        $copy->path = Str::make($path)->trim();
        return $copy;
    }

    public function arguments(): array
    {
        return $this->arguments->toArray();
    }

    public function workingDirectory(): ?string
    {
        return $this->workingDirectory?->val();
    }

    public function standardInput(): string
    {
        return $this->standardInput->val();
    }

    public function environment(): array
    {
        return $this->environment->toArray();
    }

    public function capabilities(): array
    {
        return $this->capabilities->toArray();
    }

    public function timeoutMs(): int
    {
        return (int)$this->timeoutMs->val();
    }

    public function hasCapability(string $capability): bool
    {
        return $this->capabilities->has(Str::lower($capability), true);
    }

    public function isCancelled(): bool
    {
        return Func::isCallable($this->cancelCheck) && (bool)($this->cancelCheck)();
    }

    public function hasTimedOut(): bool
    {
        return $this->timeoutMs() > 0
            && ((microtime(true) - $this->startedAt) * 1000) >= $this->timeoutMs();
    }

    public function scopedContext(BridgeContext $context): BridgeContext
    {
        $basePaths = [];
        if ($this->hasCapability(self::CAP_FILESYSTEM)) {
            $basePaths = $context->basePaths();
            if (Val::isNotEmpty($this->workingDirectory())) {
                $basePaths = Arr::merge($basePaths, [$this->workingDirectory()]);
            }
        }

        $environment = $this->hasCapability(self::CAP_ENVIRONMENT)
            ? $this->environment()
            : [];

        return $context->scoped(
            $environment,
            Arr::make($basePaths)->unique()->toArray(),
            Arr::merge($context->vars(), [
                'execution' => $this->metadata(),
                'args' => $this->arguments(),
                'stdin' => $this->standardInput(),
            ]),
            $this->hasCapability(self::CAP_FILESYSTEM) ? $context->includePaths() : []
        );
    }

    public function metadata(): array
    {
        return [
            'path' => $this->path(),
            'args' => $this->arguments(),
            'cwd' => $this->hasCapability(self::CAP_FILESYSTEM) ? $this->workingDirectory() : null,
            'env_keys' => $this->hasCapability(self::CAP_ENVIRONMENT)
                ? $this->environment->keys()->toArray()
                : [],
            'capabilities' => $this->capabilities(),
            'timeout_ms' => $this->timeoutMs(),
        ];
    }
}
