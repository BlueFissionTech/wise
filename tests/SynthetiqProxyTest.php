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
}
