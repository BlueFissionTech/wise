<?php

namespace BlueFission\Wise\Int;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class OrchestrationOutcome extends Obj
{
    private Str $status;
    private Str $pattern;
    private mixed $output;
    private Arr $workerResults;
    private Arr $conflicts;
    private ?float $confidence;
    private Arr $metadata;
    private Arr $persona;
    private Str $sessionId;
    private Arr $state;
    private Arr $contract;
    private Arr $provider;
    private Arr $providerResult;
    private Arr $subject;
    private Arr $lineage;
    private Arr $budgets;
    private Arr $lifecycle;
    private Arr $execution;
    private Arr $diagnostics;

    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->status = Str::make((string)($data['status'] ?? 'failed'));
        $this->pattern = Str::make((string)($data['pattern'] ?? ''));
        $this->output = $data['output'] ?? null;
        $this->workerResults = self::collection($data['worker_results'] ?? null);
        $this->conflicts = self::collection($data['conflicts'] ?? null);
        $this->confidence = Val::is($data['confidence'] ?? null) ? (float)$data['confidence'] : null;
        $this->metadata = self::collection($data['metadata'] ?? null);
        $this->persona = self::collection($data['persona'] ?? null);
        $this->sessionId = Str::make((string)($data['session_id'] ?? ''));
        $this->state = self::collection($data['state'] ?? null);
        $this->contract = self::collection($data['contract'] ?? null);
        $this->provider = self::collection($data['provider'] ?? null);
        $this->providerResult = self::collection($data['provider_result'] ?? null);
        $this->subject = self::collection($data['subject'] ?? null);
        $this->lineage = self::collection($data['lineage'] ?? null);
        $this->budgets = self::collection($data['budgets'] ?? null);
        $this->lifecycle = self::collection($data['lifecycle'] ?? null);
        $this->execution = self::collection($data['execution'] ?? null);
        $this->diagnostics = self::collection($data['diagnostics'] ?? null);
    }

    private static function collection(mixed $value): Arr
    {
        return Arr::is($value) ? Arr::make($value) : Arr::make();
    }

    public static function unavailable(): self
    {
        return new self([
            'status' => 'unavailable',
            'metadata' => ['reason' => 'orchestration_unavailable'],
        ]);
    }

    public static function failure(string $reason, array $metadata = []): self
    {
        return new self([
            'status' => 'failed',
            'metadata' => Arr::make($metadata)->merge(['reason' => $reason])->toArray(),
        ]);
    }

    public function status(): string
    {
        return $this->status->val();
    }

    public function successful(): bool
    {
        return Arr::has(['completed', 'success'], $this->status(), true);
    }

    public function pattern(): string
    {
        return $this->pattern->val();
    }

    public function output(): mixed
    {
        return $this->output;
    }

    public function workerResults(): array
    {
        return $this->workerResults->toArray();
    }

    public function conflicts(): array
    {
        return $this->conflicts->toArray();
    }

    public function confidence(): ?float
    {
        return $this->confidence;
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }

    public function persona(): array
    {
        return $this->persona->toArray();
    }

    public function sessionId(): string
    {
        return $this->sessionId->val();
    }

    public function state(): array
    {
        return $this->state->toArray();
    }

    public function contract(): array
    {
        return $this->contract->toArray();
    }

    public function provider(): array
    {
        return $this->provider->toArray();
    }

    public function providerResult(): array
    {
        return $this->providerResult->toArray();
    }

    public function subject(): array
    {
        return $this->subject->toArray();
    }

    public function lineage(): array
    {
        return $this->lineage->toArray();
    }

    public function budgets(): array
    {
        return $this->budgets->toArray();
    }

    public function lifecycle(): array
    {
        return $this->lifecycle->toArray();
    }

    public function execution(): array
    {
        return $this->execution->toArray();
    }

    public function diagnostics(): array
    {
        return $this->diagnostics->toArray();
    }

    public function toArray(): array
    {
        $data = [
            'status' => $this->status(),
            'pattern' => $this->pattern(),
            'output' => $this->output(),
            'worker_results' => $this->workerResults(),
            'conflicts' => $this->conflicts(),
            'confidence' => $this->confidence(),
            'metadata' => $this->metadata(),
            'persona' => $this->persona(),
            'session_id' => $this->sessionId(),
            'state' => $this->state(),
        ];

        foreach ([
            'contract' => $this->contract(),
            'provider' => $this->provider(),
            'provider_result' => $this->providerResult(),
            'subject' => $this->subject(),
            'lineage' => $this->lineage(),
            'budgets' => $this->budgets(),
            'lifecycle' => $this->lifecycle(),
            'execution' => $this->execution(),
            'diagnostics' => $this->diagnostics(),
        ] as $key => $value) {
            if ($value !== []) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
