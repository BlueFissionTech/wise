<?php

namespace BlueFission\Wise\Int;

use BlueFission\Obj;

final class OrchestrationEnvelope extends Obj
{
    public const CONTRACT_NAME = 'wise.orchestration';
    public const CONTRACT_VERSION = '1.0.0';

    private OrchestrationRequest $request;
    private array $provider;

    public function __construct(OrchestrationRequest $request, array $provider = [])
    {
        parent::__construct();
        $this->request = $request;
        $this->provider = $this->normalizeProvider($provider);
    }

    public function contract(): array
    {
        return [
            'name' => self::CONTRACT_NAME,
            'version' => self::CONTRACT_VERSION,
        ];
    }

    public function provider(): array
    {
        return $this->provider;
    }

    public function lifecycle(): array
    {
        return [
            'mode' => 'synchronous',
            'cancellation' => 'unsupported',
            'streaming' => 'unsupported',
            'resume' => 'unsupported',
            'effect_retries' => 'unsupported',
            'exactly_once_effects' => 'unsupported',
        ];
    }

    public function request(): array
    {
        $persona = $this->request->persona()->toArray();

        return [
            'contract' => $this->contract(),
            'provider' => $this->provider(),
            'subject' => [
                'id' => $persona['id'] ?? null,
                'roles' => $persona['roles'] ?? [],
                'permissions' => $persona['permissions'] ?? [],
            ],
            'session' => ['id' => $this->request->sessionId()],
            'lineage' => $this->request->lineage(),
            'budgets' => $this->request->budgets(),
            'lifecycle' => $this->lifecycle(),
            'request' => [
                'task' => $this->request->task(),
                'pattern' => $this->request->pattern(),
                'worker_ids' => array_map('strval', array_keys($this->request->workers())),
                'context' => $this->request->context(),
                'capability_scope' => $this->request->capabilities(),
                'state' => $this->request->state(),
                'config' => $this->request->config(),
            ],
        ];
    }

    public function outcome(
        array $hostResult,
        array $providerResult = [],
        array $diagnostics = []
    ): array {
        $persona = $this->request->persona()->toArray();
        $execution = is_array($hostResult['execution'] ?? null)
            ? $hostResult['execution']
            : [];
        unset($hostResult['execution']);

        return array_merge($hostResult, [
            'contract' => $this->contract(),
            'provider' => $this->provider(),
            'provider_result' => $this->normalizeProviderResult($providerResult),
            'subject' => [
                'id' => $persona['id'] ?? null,
                'roles' => $persona['roles'] ?? [],
                'permissions' => $persona['permissions'] ?? [],
            ],
            'lineage' => $this->request->lineage(),
            'budgets' => $this->request->budgets(),
            'lifecycle' => $this->lifecycle(),
            'execution' => $this->normalizeExecution($execution),
            'diagnostics' => array_values($diagnostics),
        ]);
    }

    private function normalizeProvider(array $provider): array
    {
        $contract = is_array($provider['contract'] ?? null)
            ? $provider['contract']
            : [];

        return [
            'name' => $this->nullableString($provider['name'] ?? null),
            'source' => $this->nullableString($provider['source'] ?? null),
            'version' => $this->nullableString($provider['version'] ?? null),
            'contract' => [
                'name' => $this->nullableString($contract['name'] ?? null),
                'version' => $this->nullableString($contract['version'] ?? null),
                'features' => is_array($contract['features'] ?? null)
                    ? array_values($contract['features'])
                    : [],
            ],
        ];
    }

    private function normalizeProviderResult(array $result): array
    {
        return [
            'status' => $this->nullableString($result['status'] ?? null),
            'code' => $this->nullableString($result['code'] ?? null),
            'evidence' => is_array($result['evidence'] ?? null) ? $result['evidence'] : [],
            'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
        ];
    }

    private function normalizeExecution(array $execution): array
    {
        $termination = is_array($execution['termination'] ?? null)
            ? $execution['termination']
            : [];
        $evidence = is_array($execution['evidence'] ?? null)
            ? $execution['evidence']
            : [];
        $effects = is_array($execution['effects'] ?? null)
            ? $execution['effects']
            : [];

        return [
            'state' => $this->nullableString($execution['state'] ?? null),
            'termination' => [
                'reason' => $this->nullableString($termination['reason'] ?? null),
                'requested' => $this->nullableBool($termination, 'requested'),
                'confirmed_stopped' => $this->nullableBool($termination, 'confirmed_stopped'),
                'mechanism' => $this->nullableString($termination['mechanism'] ?? null),
            ],
            'evidence' => [
                'in_flight' => is_array($evidence['in_flight'] ?? null)
                    ? array_values($evidence['in_flight'])
                    : null,
                'uncertain' => is_array($evidence['uncertain'] ?? null)
                    ? array_values($evidence['uncertain'])
                    : null,
            ],
            'effects' => [
                'authorization_owner' => 'host',
                'idempotency_owner' => 'host',
                'attributed_after_terminal' => is_array($effects['attributed_after_terminal'] ?? null)
                    ? array_values($effects['attributed_after_terminal'])
                    : null,
            ],
        ];
    }

    private function nullableBool(array $values, string $key): ?bool
    {
        return array_key_exists($key, $values) && is_bool($values[$key])
            ? $values[$key]
            : null;
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
