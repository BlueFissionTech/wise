<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Int\AutomataOrchestrator;
use BlueFission\Wise\Int\NullOrchestrator;
use BlueFission\Wise\Int\OrchestrationEnvelope;
use BlueFission\Wise\Int\OrchestrationOutcome;
use BlueFission\Wise\Int\OrchestrationRequest;
use BlueFission\Wise\Int\PersonaContext;
use BlueFission\Wise\Usr\Profile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AutomataOrchestratorTest extends TestCase
{
    public function testPersonaContextNormalizesProfileData(): void
    {
        $persona = PersonaContext::fromProfile(
            new Profile('agent-1', ['Planner', ' planner ', 'Reviewer']),
            ['mode' => 'careful']
        );

        $this->assertSame('agent-1', $persona->id());
        $this->assertSame(['planner', 'reviewer'], $persona->roles());
        $this->assertSame([], $persona->permissions());
        $this->assertSame('careful', $persona->toArray()['attributes']['mode']);
    }

    public function testNullOrchestratorReturnsUnavailableOutcome(): void
    {
        $orchestrator = new NullOrchestrator();

        $outcome = $orchestrator->orchestrate(new OrchestrationRequest(
            'inspect the workspace',
            new Profile('agent-1')
        ));

        $this->assertFalse($orchestrator->available());
        $this->assertSame('unavailable', $outcome->status());
        $this->assertSame('automata_orchestration_unavailable', $outcome->metadata()['reason']);
    }

    public function testAutomataSequentialOrchestrationReturnsStructuredState(): void
    {
        $orchestrator = new AutomataOrchestrator();
        $request = new OrchestrationRequest(
            'prepare and verify a change',
            new Profile('agent-1', ['operator']),
            workers: [
                'plan' => static fn (array $context): array => [
                    'output' => [
                        'steps' => ['inspect', 'change', 'verify'],
                        'contract_version' => $context['wise_envelope']['contract']['version'] ?? null,
                    ],
                    'confidence' => 0.9,
                ],
                'verify' => static fn (array $context, array $prior): array => [
                    'output' => [
                        'verified' => isset($context['plan']['steps']),
                        'prior_count' => count($prior),
                    ],
                    'confidence' => 1.0,
                ],
            ],
            context: ['workspace' => 'current'],
            capabilities: ['Resource.Read', 'resource.execute'],
            sessionId: 'session-1'
        );

        $outcome = $orchestrator->orchestrate($request);

        $this->assertTrue($orchestrator->available());
        $this->assertTrue($outcome->successful());
        $this->assertSame('sequential', $outcome->pattern());
        $this->assertSame([], $outcome->conflicts());
        $this->assertSame(0.95, $outcome->confidence());
        $this->assertSame('session-1', $outcome->sessionId());
        $this->assertSame('agent-1', $outcome->persona()['id']);
        $this->assertCount(2, $outcome->workerResults());
        $this->assertTrue($outcome->output()['verify']['verified']);
        $this->assertSame(OrchestrationEnvelope::CONTRACT_VERSION, $outcome->output()['plan']['contract_version']);
        $this->assertSame('prepare and verify a change', $outcome->state()['channels']['observations']['task']);
        $this->assertSame('agent-1', $outcome->state()['channels']['rules']['persona']['id']);
        $this->assertSame('automata', $outcome->provider()['name']);
        $this->assertSame('1.5.0', $outcome->provider()['contract']['version']);
        $this->assertSame('completed', $outcome->providerResult()['status']);
        $this->assertSame('unsupported', $outcome->lifecycle()['cancellation']);
    }

    public function testAutomataOrchestrationCanBeDisabled(): void
    {
        $orchestrator = new AutomataOrchestrator(enabled: false);

        $outcome = $orchestrator->orchestrate(new OrchestrationRequest('task', new Profile('agent-1')));

        $this->assertFalse($orchestrator->available());
        $this->assertSame('unavailable', $outcome->status());
    }

    public function testAutomataFactoryFailuresAreNormalized(): void
    {
        $orchestrator = new AutomataOrchestrator(
            static function (array $config): object {
                throw new \RuntimeException('runtime details');
            }
        );

        $outcome = $orchestrator->orchestrate(new OrchestrationRequest('task', new Profile('agent-1')));

        $this->assertSame('failed', $outcome->status());
        $this->assertSame('automata_orchestration_failed', $outcome->metadata()['reason']);
        $this->assertSame(\RuntimeException::class, $outcome->metadata()['exception']);
        $this->assertSame('provider_exception', $outcome->providerResult()['code']);
        $this->assertSame('provider_exception', $outcome->diagnostics()[0]['code']);
        $this->assertStringNotContainsString('runtime details', json_encode($outcome->toArray()));
    }

    public function testAutomataFactoryMustReturnReleasedOrchestrator(): void
    {
        $orchestrator = new AutomataOrchestrator(static fn (array $config): object => new \stdClass());

        $outcome = $orchestrator->orchestrate(new OrchestrationRequest('task', new Profile('agent-1')));

        $this->assertSame('failed', $outcome->status());
        $this->assertSame('invalid_automata_orchestrator', $outcome->metadata()['reason']);
    }

    #[DataProvider('providerFreeOutcomeFixtures')]
    public function testProviderFreeEnvelopeKeepsHostAndProviderStatusesSeparate(
        string $hostStatus,
        string $providerStatus,
        ?string $providerCode
    ): void {
        $request = new OrchestrationRequest(
            'inspect the workspace',
            new Profile('agent-1', ['operator']),
            workers: ['inspect' => static fn (): array => []],
            capabilities: ['resource.read'],
            sessionId: 'session-1',
            lineage: [
                'trace_id' => 'trace-1',
                'correlation_id' => 'correlation-1',
                'causation_id' => 'causation-1',
            ],
            budgets: ['attempts' => 1]
        );
        $envelope = new OrchestrationEnvelope($request);

        $outcome = new OrchestrationOutcome($envelope->outcome([
            'status' => $hostStatus,
        ], [
            'status' => $providerStatus,
            'code' => $providerCode,
        ]));

        $this->assertSame(OrchestrationEnvelope::CONTRACT_VERSION, $outcome->contract()['version']);
        $this->assertSame($hostStatus, $outcome->status());
        $this->assertSame($providerStatus, $outcome->providerResult()['status']);
        $this->assertSame($providerCode, $outcome->providerResult()['code']);
        $this->assertSame('agent-1', $outcome->subject()['id']);
        $this->assertSame('trace-1', $outcome->lineage()['trace_id']);
        $this->assertSame(['attempts' => 1], $outcome->budgets());
        $this->assertSame('unsupported', $outcome->lifecycle()['streaming']);
        $this->assertSame(['inspect'], $envelope->request()['request']['worker_ids']);
    }

    public static function providerFreeOutcomeFixtures(): array
    {
        return [
            'normal' => ['completed', 'completed', null],
            'error' => ['failed', 'failed', 'provider_error'],
            'denied' => ['failed', 'denied', 'capability_denied'],
            'unsupported' => ['unavailable', 'unsupported', 'provider_unavailable'],
        ];
    }

    public function testProviderFreeResourceLimitFixturesKeepTerminationEvidenceHostOwned(): void
    {
        $envelope = new OrchestrationEnvelope(new OrchestrationRequest(
            'run within a bounded budget',
            new Profile('agent-1'),
            lineage: [
                'trace_id' => 'trace-limit',
                'correlation_id' => 'correlation-limit',
                'causation_id' => 'causation-limit',
            ],
            budgets: ['duration_ms' => 250]
        ));

        $confirmed = new OrchestrationOutcome($envelope->outcome([
            'status' => 'failed',
            'execution' => [
                'state' => 'stopped',
                'termination' => [
                    'reason' => 'resource_limit',
                    'requested' => true,
                    'confirmed_stopped' => true,
                    'mechanism' => 'host_budget_guard',
                ],
                'effects' => ['attributed_after_terminal' => []],
            ],
        ], [
            'status' => 'failed',
            'code' => 'resource_limit',
        ]));

        $unsupported = new OrchestrationOutcome($envelope->outcome([
            'status' => 'unavailable',
            'execution' => [
                'state' => 'uncertain',
                'termination' => [
                    'reason' => 'cancellation_requested',
                    'requested' => true,
                    'confirmed_stopped' => false,
                    'mechanism' => 'unsupported',
                ],
                'evidence' => [
                    'in_flight' => ['worker-1'],
                    'uncertain' => ['termination_confirmation'],
                ],
                'effects' => ['attributed_after_terminal' => []],
            ],
        ], [
            'status' => 'unsupported',
            'code' => 'cancellation_unsupported',
        ]));

        $this->assertTrue($confirmed->execution()['termination']['requested']);
        $this->assertTrue($confirmed->execution()['termination']['confirmed_stopped']);
        $this->assertSame('host_budget_guard', $confirmed->execution()['termination']['mechanism']);
        $this->assertSame([], $confirmed->execution()['effects']['attributed_after_terminal']);

        $this->assertFalse($unsupported->execution()['termination']['confirmed_stopped']);
        $this->assertSame(['worker-1'], $unsupported->execution()['evidence']['in_flight']);
        $this->assertSame(['termination_confirmation'], $unsupported->execution()['evidence']['uncertain']);
        $this->assertSame('host', $unsupported->execution()['effects']['authorization_owner']);
        $this->assertSame('host', $unsupported->execution()['effects']['idempotency_owner']);
        $this->assertSame([], $unsupported->execution()['effects']['attributed_after_terminal']);
        $this->assertSame('unsupported', $unsupported->lifecycle()['cancellation']);
    }
}
