<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\ExecutionRequest;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Exe\VibeBridge;
use PHPUnit\Framework\TestCase;

final class ExeExecutionRequestTest extends TestCase
{
    public function testCapabilitiesProjectOnlyExplicitExecutionContext(): void
    {
        $context = new BridgeContext(
            env: ['HOST_SECRET' => 'hidden'],
            basePaths: ['base'],
            includePaths: ['include'],
            vars: ['existing' => true]
        );
        $request = new ExecutionRequest(
            'task.jss',
            ['one'],
            'work',
            'input',
            ['SAFE' => 'yes'],
            [ExecutionRequest::CAP_ENVIRONMENT, ExecutionRequest::CAP_FILESYSTEM]
        );

        $scoped = $request->scopedContext($context);

        $this->assertSame(['SAFE' => 'yes'], $scoped->env());
        $this->assertSame(['base', 'work'], $scoped->basePaths());
        $this->assertSame(['include'], $scoped->includePaths());
        $this->assertSame(['one'], $scoped->vars()['args']);
        $this->assertSame('input', $scoped->vars()['stdin']);
        $this->assertSame(['SAFE'], $scoped->vars()['execution']['env_keys']);
    }

    public function testMissingCapabilitiesRemoveHostAccessContext(): void
    {
        $context = new BridgeContext(
            env: ['HOST_SECRET' => 'hidden'],
            basePaths: ['base'],
            includePaths: ['include']
        );
        $request = new ExecutionRequest('task.jss', environment: ['SAFE' => 'yes']);

        $scoped = $request->scopedContext($context);

        $this->assertSame([], $scoped->env());
        $this->assertSame([], $scoped->basePaths());
        $this->assertSame([], $scoped->includePaths());
        $this->assertSame([], $request->metadata()['env_keys']);
    }

    public function testCancellationStopsJenssBeforeRuntimeExecution(): void
    {
        $request = new ExecutionRequest('task.jss', cancelCheck: static fn(): bool => true);

        $result = (new JenssBridge())->execute($request, new BridgeContext());

        $this->assertFalse($result->successFlag());
        $this->assertSame('JenSS execution cancelled.', $result->output());
        $this->assertTrue($result->meta()['cancelled']);
    }

    public function testRegistryRejectsBridgeWithoutExecutionContract(): void
    {
        $registry = new BridgeRegistry();
        $registry->register(new VibeBridge());

        $result = $registry->execute(new ExecutionRequest('template.vibe'), new BridgeContext());

        $this->assertFalse($result->successFlag());
        $this->assertStringContainsString('No executing bridge registered', $result->output());
    }
}
