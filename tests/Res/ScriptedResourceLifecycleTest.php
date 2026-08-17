<?php

namespace BlueFission\Tests\Res;

use BlueFission\Str;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\BridgeResult;
use BlueFission\Wise\Exe\IBridge;
use BlueFission\Wise\Res\ScriptedResourceDefinition;
use BlueFission\Wise\Res\ScriptedResourceRunner;
use BlueFission\Wise\Res\ScriptedResourceStore;
use PHPUnit\Framework\TestCase;

final class ScriptedResourceLifecycleTest extends TestCase
{
    public function testRunnerNormalizesArgumentsAndBuildsStableBridgeValues(): void
    {
        $bridge = new CapturingScriptBridge();
        $registry = new BridgeRegistry();
        $registry->register($bridge);
        $store = new InMemoryScriptedResourceStore();
        $runner = new ScriptedResourceRunner(new ScriptedResourceKernel(), $registry, $store);
        $definition = new ScriptedResourceDefinition('message', 'message.jss', ['send']);

        $output = $runner->run($definition, 'send', ['new', 'message', 'operator', 'hello']);

        $this->assertSame('script complete', $output);
        $this->assertSame('operator', $bridge->vars['recipient']);
        $this->assertSame('hello', $bridge->vars['content']);
        $this->assertSame(1, $bridge->vars['count']);
        $this->assertSame(1, $bridge->vars['total']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $bridge->vars['entry']['created_at']);
    }

    public function testRunnerReturnsDeterministicFailureWithoutRegisteredBridge(): void
    {
        $runner = new ScriptedResourceRunner(
            new ScriptedResourceKernel(),
            new BridgeRegistry(),
            new InMemoryScriptedResourceStore()
        );
        $definition = new ScriptedResourceDefinition('message', 'message.jss', ['list']);

        $this->assertSame('No script bridge registered for message.', $runner->run($definition, 'list', []));
    }
}

final class ScriptedResourceKernel extends Kernel
{
    public function __construct()
    {
    }

    public function buildBridgeContext(array &$messages, array $vars = []): BridgeContext
    {
        return new BridgeContext(vars: $vars);
    }
}

final class CapturingScriptBridge implements IBridge
{
    public array $vars = [];

    public function name(): string
    {
        return 'capture';
    }

    public function extensions(): array
    {
        return ['jss'];
    }

    public function canHandleFile(string $path): bool
    {
        return Str::endsWith($path, '.jss');
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->vars = $context->vars();
        return BridgeResult::success('script complete');
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        return BridgeResult::failure('unsupported');
    }
}

final class InMemoryScriptedResourceStore extends ScriptedResourceStore
{
    private array $entries = [];

    public function list(string $resource): array
    {
        return $this->entries[$resource] ?? [];
    }

    public function save(string $resource, array $entries): void
    {
        $this->entries[$resource] = $entries;
    }
}
