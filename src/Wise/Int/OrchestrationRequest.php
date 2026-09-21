<?php

namespace BlueFission\Wise\Int;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Usr\Profile;

class OrchestrationRequest extends Obj
{
    private Str $task;
    private PersonaContext $persona;
    private Str $pattern;
    private Str $sessionId;
    private Arr $context;
    private Arr $workers;
    private Arr $capabilities;
    private Arr $state;
    private Arr $config;
    private Arr $lineage;
    private Arr $budgets;

    public function __construct(
        string $task,
        Profile|PersonaContext $persona,
        array $workers = [],
        string $pattern = 'sequential',
        array $context = [],
        array $capabilities = [],
        array $state = [],
        array $config = [],
        ?string $sessionId = null,
        array $lineage = [],
        array $budgets = []
    ) {
        parent::__construct();
        $this->task = Str::make($task)->trim();
        $this->persona = $persona instanceof PersonaContext
            ? $persona
            : PersonaContext::fromProfile($persona);
        $this->pattern = Str::make($pattern)->trim()->lower();
        $this->sessionId = Str::make($sessionId ?? '')->trim();
        $this->context = Arr::make($context);
        $this->workers = Arr::make($workers);
        $this->capabilities = Arr::make($capabilities);
        $this->state = Arr::make($state);
        $this->config = Arr::make($config);
        $this->lineage = Arr::make($lineage);
        $this->budgets = Arr::make($budgets);
    }

    public function task(): string
    {
        return $this->task->val();
    }

    public function persona(): PersonaContext
    {
        return $this->persona;
    }

    public function pattern(): string
    {
        return $this->pattern->val();
    }

    public function sessionId(): ?string
    {
        return $this->sessionId->isNotEmpty() ? $this->sessionId->val() : null;
    }

    public function context(): array
    {
        return $this->context->toArray();
    }

    public function workers(): array
    {
        return $this->workers->toArray();
    }

    public function capabilities(): array
    {
        return $this->capabilities->toArray();
    }

    public function state(): array
    {
        return $this->state->toArray();
    }

    public function config(): array
    {
        return $this->config->toArray();
    }

    public function lineage(): array
    {
        $lineage = $this->lineage->toArray();

        return [
            'trace_id' => $this->nullableString($lineage['trace_id'] ?? null),
            'correlation_id' => $this->nullableString($lineage['correlation_id'] ?? null),
            'causation_id' => $this->nullableString($lineage['causation_id'] ?? null),
        ];
    }

    public function budgets(): array
    {
        return $this->budgets->toArray();
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
