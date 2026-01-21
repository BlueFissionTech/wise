<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Sys\FileSystemManager;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Sys\KeyInputManager;
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

final class JenssBridgePipelineTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-jenss-' . uniqid();
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cmd', 0777, true);

        $source = file_get_contents(__DIR__ . '/../../examples/root/cmd/hello.jss');
        file_put_contents(
            $this->root . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'hello.jss',
            $source !== false ? $source : ''
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testJenssScriptRunsThroughBridge(): void
    {
        if (!class_exists(\BlueFission\Jenerator\Parsing\JenssParser::class)
            && !class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)) {
            $this->markTestSkipped('JenSS interpreter not available.');
        }

        $kernel = $this->makeKernel();
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('hello');
        $output = $kernel->lastOutput();

        $this->assertStringContainsString('Hello from Wise.', $output);
    }

    private function makeKernel(): JenssTestKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $displayDriver = new JenssNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), new KeyInputManager());

        return new JenssTestKernel(
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

final class JenssTestKernel extends Kernel
{
    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class JenssNullDisplayDriver implements IDisplayDriver
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
