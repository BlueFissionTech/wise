<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Nav\SynthetiqProxy;
use PHPUnit\Framework\TestCase;

final class SynthetiqProxyTest extends TestCase
{
    public function testProcessDelegatesToSynthetiq(): void
    {
        $synthetiq = new class {
            public function processInput(string $input): string
            {
                return 'response:' . $input;
            }
        };

        $engine = new SynthetiqProxy($synthetiq);

        $this->assertSame('response:hello', $engine->process('hello'));
    }

    public function testConstructorRequiresProcessInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SynthetiqProxy(new \stdClass());
    }

    public function testHandoffUsesStructuredEnvelopeWhenAvailable(): void
    {
        $synthetiq = new class {
            public function processInput(string $input): string
            {
                return 'fallback:' . $input;
            }

            public function processInputEnvelope(string $input): array
            {
                return [
                    'response' => 'envelope:' . $input,
                    'current_intent' => 'wise.test',
                    'confidence' => 0.95,
                ];
            }
        };

        $handoff = (new SynthetiqProxy($synthetiq))->handoff('hello')->toArray();

        $this->assertSame('wise.test', $handoff['current_intent']);
        $this->assertSame(0.95, $handoff['confidence']);
        $this->assertSame('synthetiq.processInputEnvelope', $handoff['provenance']['source']);
    }
}
