<?php

namespace BlueFission\Tests;

use BlueFission\Arr;
use BlueFission\Wise\Nav\SynthetiqContextHandoff;
use BlueFission\Wise\Nav\SynthetiqProxy;
use PHPUnit\Framework\TestCase;

final class SynthetiqContextHandoffTest extends TestCase
{
    public function testDeterministicFixtureDeclaresWiseAndSynthetiqFields(): void
    {
        $first = SynthetiqContextHandoff::deterministicFixture();
        $second = SynthetiqContextHandoff::deterministicFixture();
        $payload = $first->toArray();

        $this->assertTrue($first->isAccepted());
        $this->assertSame($first->outputId(), $second->outputId());
        $this->assertSame([], $payload['declared_capabilities']);
        $this->assertSame('wise.route.classify', $payload['current_intent']);
        $this->assertSame('side_effect_free', $payload['safety_policy']);
        $this->assertSame(0, $payload['exit_status']);

        foreach (SynthetiqContextHandoff::handoffFieldNames() as $field) {
            $this->assertTrue(Arr::hasKey($payload, $field), "Missing handoff field {$field}");
        }

        foreach (SynthetiqContextHandoff::wiseEnvelopeFieldNames() as $field) {
            $this->assertTrue(Arr::hasKey($payload, $field), "Missing Wise envelope field {$field}");
        }
    }

    public function testProxyBuildsAcceptedHandoffFromSideEffectFreeRouteClassification(): void
    {
        $synthetiq = new class {
            public function processInput(string $input): array
            {
                return [
                    'response' => 'route accepted for ' . $input,
                    'current_intent' => 'wise.route.classify',
                    'confidence' => 1.5,
                ];
            }
        };

        $handoff = (new SynthetiqProxy($synthetiq))->handoff('list all resources', [
            'conversation_profile' => ['id' => 'operator'],
            'context_refs' => [['type' => 'route', 'ref' => 'wise.resources']],
            'declared_capabilities' => [],
            'session_id' => 'session-1',
            'scope' => 'user',
            'safety_policy' => 'side_effect_free',
        ]);
        $payload = $handoff->toArray();

        $this->assertSame(SynthetiqContextHandoff::STATUS_ACCEPTED, $handoff->status());
        $this->assertSame(1.0, $payload['confidence']);
        $this->assertSame([], $payload['declared_capabilities']);
        $this->assertSame('synthetiq.processInput', $payload['provenance']['source']);
        $this->assertSame('route accepted for list all resources', $payload['diagnostics']['response_preview']);
        $this->assertMatchesRegularExpression('/^wise-out-[a-f0-9]{16}$/', $payload['output_id']);
    }

    public function testProxyBuildsFailureHandoffForUnavailableRuntime(): void
    {
        $synthetiq = new class {
            public function processInput(string $input): string
            {
                throw new \RuntimeException('classifier unavailable');
            }
        };

        $payload = (new SynthetiqProxy($synthetiq))
            ->handoff('list all resources', ['session_id' => 'session-1'])
            ->toArray();

        $this->assertSame(SynthetiqContextHandoff::STATUS_FAILURE, $payload['handoff_status']);
        $this->assertSame('failed', $payload['execution_state']);
        $this->assertSame(1, $payload['exit_status']);
        $this->assertSame('classifier unavailable', $payload['diagnostics']['message']);
        $this->assertSame('RuntimeException', $payload['diagnostics']['exception']);
    }
}
