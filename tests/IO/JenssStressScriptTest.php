<?php

namespace BlueFission\Tests\IO;

use BlueFission\Automata\Language\{
    Interpreter,
    Grammar,
    StemmerLemmatizer,
    Documenter,
    Walker
};
use BlueFission\Data\Storage\Memory;
use BlueFission\IPC\IPC;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Sys\FileSystemManager;
use BlueFission\Wise\Sys\IO\CommandInputStream;
use BlueFission\Wise\Sys\KeyInputManager;
use BlueFission\Wise\Sys\MemoryManager;
use PHPUnit\Framework\TestCase;

final class JenssStressScriptTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-jenss-stress-' . uniqid();
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cmd', 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cfg' . DIRECTORY_SEPARATOR . 'agent', 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cfg' . DIRECTORY_SEPARATOR . 'routing', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testAgentReadinessScriptBuildsStatementAndFeedbackSummary(): void
    {
        $this->requireJenss();
        $this->copyExample('cmd/agent-readiness.jss');

        $kernel = $this->makeKernel([
            'Harden CLI contracts',
            'command processor',
            'bridge output not reviewed',
        ]);
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('agent-readiness');
        $output = $kernel->lastOutput();

        $this->assertStringContainsString('Agent readiness review.', $output);
        $this->assertStringContainsString('Goal: Prepare the agent environment', $output);
        $this->assertStringContainsString('Objective: Harden CLI contracts', $output);
        $this->assertStringContainsString('Resource: command processor', $output);
        $this->assertStringContainsString('Risk: bridge output not reviewed', $output);
        $this->assertStringContainsString('Resource claim:', $output);
        $this->assertStringContainsString('Risk claim:', $output);
        $this->assertStringContainsString('Readiness score:', $output);
    }

    public function testCommandPredictionScriptTrainsReusableSuggestionModel(): void
    {
        $this->requireJenss();
        $this->copyExample('cmd/predict-command.jss');

        $kernel = $this->makeKernel();
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('predict-command');
        $output = $kernel->lastOutput();

        $this->assertStringContainsString('Command suggestion training.', $output);
        $this->assertStringContainsString('Seeded command history: 5', $output);
        $this->assertStringContainsString('Suggestion:', $output);
    }

    public function testResourceEventScriptShapesOutputEnvelopeMetadata(): void
    {
        $this->requireJenss();
        $this->copyExample('cmd/resource-event.jss');

        $kernel = $this->makeKernel();
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('resource-event');
        $output = $kernel->lastOutput();

        $this->assertStringContainsString('Resource event envelope.', $output);
        $this->assertStringContainsString('Output id: out-001', $output);
        $this->assertStringContainsString('Resource: command', $output);
        $this->assertStringContainsString('Status: waiting', $output);
        $this->assertStringContainsString('Prompt state: suspended', $output);
        $this->assertStringContainsString('Waiting state: waiting_for_output', $output);
        $this->assertStringContainsString('Semantic metadata:', $output);
    }

    public function testConfigScriptsRunThroughJenssBridge(): void
    {
        $this->requireJenss();
        $this->copyExample('cfg/agent/default.jss');
        $this->copyExample('cfg/routing/default.jss');

        $bridge = new JenssBridge();
        $context = new BridgeContext(null, null, [], [$this->root]);

        foreach ([
            'cfg/agent/default.jss',
            'cfg/routing/default.jss',
        ] as $relativePath) {
            $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $result = $bridge->runFile($path, $context);

            $this->assertTrue($result->successFlag(), $result->output());
            $this->assertSame('', trim($result->output()));
        }
    }

    private function makeKernel(array $responses = []): JenssStressKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $inputStream = new CommandInputStream($responses, false);
        $keyInput = new KeyInputManager($inputStream, true);
        $displayDriver = new JenssStressNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), $keyInput);
        $console->registerInputChannel('system');

        return new JenssStressKernel(
            $processManager,
            new CommandProcessor(new Memory(), null, null),
            $memoryManager,
            $fileSystem,
            $interpreter,
            $console,
            new Memory(),
            new Memory(),
            new IPC(new Memory())
        );
    }

    private function copyExample(string $relativePath): void
    {
        $sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples'
            . DIRECTORY_SEPARATOR . 'root'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $targetPath = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $targetDir = dirname($targetPath);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $source = file_get_contents($sourcePath);
        file_put_contents($targetPath, $source !== false ? $source : '');
    }

    private function requireJenss(): void
    {
        if (!class_exists(\BlueFission\Jenerator\Parsing\JenssParser::class)
            && !class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)) {
            $this->markTestSkipped('JenSS interpreter not available.');
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    private function resetKernelInstance(): void
    {
        $reflection = new \ReflectionClass(Kernel::class);
        if (!$reflection->hasProperty('_instance')) {
            return;
        }

        $property = $reflection->getProperty('_instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}

final class JenssStressKernel extends Kernel
{
    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class JenssStressNullDisplayDriver implements IDisplayDriver
{
    public function handle($data, $type = null, $style = null): void
    {
    }

    public function send($data): void
    {
    }

    public function getContent(): array
    {
        return [];
    }

    public function flush(): void
    {
    }

    public function getTerminalSize(): array
    {
        return [80, 24];
    }

    public function init(): void
    {
    }

    public function update(): void
    {
    }

    public function draw(): void
    {
    }

    public function print(): void
    {
    }

    public function clear(): void
    {
    }

    public function clearScreen(): void
    {
    }
}
