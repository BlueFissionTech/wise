<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Int\AutomataOrchestrator;
use BlueFission\Wise\Int\NullOrchestrator;
use BlueFission\Wise\Int\OrchestrationRequest;
use BlueFission\Wise\Int\PersonaContext;
use BlueFission\Wise\Usr\Profile;
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
                    'output' => ['steps' => ['inspect', 'change', 'verify']],
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
        $this->assertSame('prepare and verify a change', $outcome->state()['channels']['observations']['task']);
        $this->assertSame('agent-1', $outcome->state()['channels']['rules']['persona']['id']);
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
        $this->assertStringNotContainsString('runtime details', json_encode($outcome->toArray()));
    }

    public function testAutomataFactoryMustReturnReleasedOrchestrator(): void
    {
        $orchestrator = new AutomataOrchestrator(static fn (array $config): object => new \stdClass());

        $outcome = $orchestrator->orchestrate(new OrchestrationRequest('task', new Profile('agent-1')));

        $this->assertSame('failed', $outcome->status());
        $this->assertSame('invalid_automata_orchestrator', $outcome->metadata()['reason']);
    }
}
