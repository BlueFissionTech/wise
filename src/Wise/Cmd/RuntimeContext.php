<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Exe\ExecutionRequest;

final class RuntimeContext extends Obj
{
    private Arr $metadata;
    private Arr $arguments;
    private ?Str $workingDirectory;
    private Arr $environment;
    private Arr $environmentAllowlist;
    private Arr $actor;
    private Arr $capabilities;
    private Num $timeoutMs;
    private ?float $deadlineAt;
    private mixed $cancelCheck;
    private float $startedAt;

    public function __construct(
        array $metadata = [],
        array $arguments = [],
        ?string $workingDirectory = null,
        array $environment = [],
        array $environmentAllowlist = [],
        array $actor = [],
        array $capabilities = [],
        int $timeoutMs = 0,
        ?float $deadlineAt = null,
        ?callable $cancelCheck = null
    ) {
        parent::__construct();
        $this->metadata = Arr::make($metadata);
        $this->arguments = Arr::make($arguments);
        $this->workingDirectory = Str::isNotEmpty((string)$workingDirectory)
            ? Str::make((string)$workingDirectory)->trim()
            : null;
        $this->environment = Arr::make($environment);
        $this->environmentAllowlist = Arr::make($environmentAllowlist)
            ->map(fn ($key) => Str::make((string)$key)->trim()->val())
            ->filter(fn ($key) => Str::isNotEmpty((string)$key))
            ->unique();
        $this->actor = Arr::make($actor);
        $this->capabilities = Arr::make($capabilities)
            ->map(fn ($capability) => Str::make((string)$capability)->trim()->lower()->val())
            ->filter(fn ($capability) => Str::isNotEmpty((string)$capability))
            ->unique();
        $this->timeoutMs = Num::make(Num::max(0, $timeoutMs));
        $this->deadlineAt = $deadlineAt;
        $this->cancelCheck = $cancelCheck;
        $this->startedAt = microtime(true);
    }

    public function metadata(): array
    {
        return Arr::merge($this->metadata->toArray(), [
            'actor' => $this->actor->toArray(),
            'capabilities' => $this->capabilities->toArray(),
        ]);
    }

    public function arguments(): array
    {
        return $this->arguments->toArray();
    }

    public function workingDirectory(): ?string
    {
        return $this->workingDirectory?->val();
    }

    public function environment(): array
    {
        return $this->environment
            ->copy()
            ->filter(fn ($value, $key) => $this->environmentAllowlist->has((string)$key, true))
            ->toArray();
    }

    public function hasEnvironment(): bool
    {
        return $this->environment->isNotEmpty();
    }

    public function capabilities(): array
    {
        return $this->capabilities->toArray();
    }

    public function hasCapability(string $capability): bool
    {
        return $this->capabilities->has(Str::lower($capability), true);
    }

    public function cancelled(): bool
    {
        return Func::isCallable($this->cancelCheck) && (bool)($this->cancelCheck)();
    }

    public function timedOut(): bool
    {
        $elapsedTimeout = (int)$this->timeoutMs->val() > 0
            && ((microtime(true) - $this->startedAt) * 1000) >= (int)$this->timeoutMs->val();
        $deadlineExpired = $this->deadlineAt !== null && microtime(true) >= $this->deadlineAt;

        return $elapsedTimeout || $deadlineExpired;
    }

    public function executionRequest(string $path, string $standardInput = ''): ExecutionRequest
    {
        return new ExecutionRequest(
            $path,
            $this->arguments(),
            $this->workingDirectory(),
            $standardInput,
            $this->environment(),
            $this->capabilities(),
            (int)$this->timeoutMs->val(),
            Func::isCallable($this->cancelCheck) ? $this->cancelCheck : null
        );
    }
}
