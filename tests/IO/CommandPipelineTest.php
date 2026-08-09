<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\BridgeResult;
use BlueFission\Wise\Exe\IBridge;
use BlueFission\Wise\Exe\IValidatingBridge;
use BlueFission\Wise\Sys\FileSystemManager;
use BlueFission\Wise\Sys\DirectoryManager;
use BlueFission\Wise\Sys\KeyInputManager;
use BlueFission\Wise\Sys\IO\CommandInputStream;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Sys\MemoryManager;
use BlueFission\Automata\Language\{
    Interpreter,
    Grammar,
    StemmerLemmatizer,
    Documenter,
    Walker
};
use BlueFission\Data\Storage\Memory;
use BlueFission\IPC\IPC;
use PHPUnit\Framework\TestCase;

final class CommandPipelineTest extends TestCase
{
    private string $root;
    private string $inputFile;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-pipe-' . uniqid();
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cmd', 0777, true);

        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'hello.jss', '#!jenss');
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'status.jss', '#!jenss');

        $this->inputFile = $this->root . DIRECTORY_SEPARATOR . 'input.txt';
        file_put_contents($this->inputFile, "hello\nstatus\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testPipedCommandsResolveScriptsFromVirtualRoot(): void
    {
        $kernel = $this->makeKernel();
        $bridge = new FakeBridge();
        $registry = new BridgeRegistry();
        $registry->register($bridge);
        $kernel->setBridgeRegistry($registry);

        $inputStream = CommandInputStream::fromFile($this->inputFile);
        $keyInput = new KeyInputManager($inputStream, true);

        $outputs = [];
        while (true) {
            $chunk = $keyInput->capture();
            if ($chunk === '') {
                break;
            }

            $command = trim($chunk);
            if ($command === '') {
                continue;
            }
            if ($command === 'exit') {
                break;
            }

            $kernel->handle($command);
            $outputs[] = $kernel->lastOutput();
        }

        $this->assertSame([
            'ran:hello.jss',
            'ran:status.jss',
        ], $outputs);

        $paths = $bridge->paths();
        $this->assertCount(2, $paths);
        $this->assertStringContainsString(DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'hello.jss', $paths[0]);
        $this->assertStringContainsString(DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'status.jss', $paths[1]);
    }

    public function testExitRequestStopsKernelWithoutCommandDispatch(): void
    {
        $kernel = $this->makeKernel();
        $kernel->markRunningForTest();

        $kernel->handle('exit');

        $this->assertSame('Goodbye.', $kernel->lastOutput());
        $this->assertFalse($kernel->isRunning());
    }

    public function testValidateCommandParsesScriptWithoutExecutingIt(): void
    {
        $kernel = $this->makeKernel();
        $bridge = new FakeBridge();
        $registry = new BridgeRegistry();
        $registry->register($bridge);
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('validate cmd/hello.jss');

        $this->assertSame('Script is valid.', $kernel->lastOutput());
        $this->assertSame([], $bridge->paths());
        $this->assertCount(1, $bridge->validatedPaths());
        $this->assertStringEndsWith('cmd' . DIRECTORY_SEPARATOR . 'hello.jss', $bridge->validatedPaths()[0]);
    }

    private function makeKernel(): TestKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $displayDriver = new NullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), new KeyInputManager());

        return new TestKernel(
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

    private function removeDir(string $dir): void
    {
        if (!DirectoryManager::pathExists($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
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

final class TestKernel extends Kernel
{
    public function markRunningForTest(): void
    {
        $this->_running = true;
    }

    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class FakeBridge implements IBridge, IValidatingBridge
{
    private array $paths = [];
    private array $validatedPaths = [];

    public function name(): string
    {
        return 'fake';
    }

    public function extensions(): array
    {
        return ['jss'];
    }

    public function canHandleFile(string $path): bool
    {
        return str_ends_with($path, '.jss');
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->paths[] = $path;
        return BridgeResult::success('ran:' . basename($path));
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        $label = $path ? basename($path) : 'source';
        return BridgeResult::success('ran:' . $label);
    }

    public function validateFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->validatedPaths[] = $path;

        return BridgeResult::success('Script is valid.', [
            'path' => $path,
            'mode' => 'validate',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return array<int, string>
     */
    public function validatedPaths(): array
    {
        return $this->validatedPaths;
    }
}

final class NullDisplayDriver implements IDisplayDriver
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
