<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\VibeBridge;
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

final class VibeBridgePipelineTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-vibe-' . uniqid();
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cmd', 0777, true);

        file_put_contents(
            $this->root . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'greet.vibe',
            "Vibe ready."
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testVibeScriptRunsThroughBridge(): void
    {
        if ($this->shouldSkipMixRegistry()) {
            $this->markTestSkipped('Vibe mix tags require TagRegistry regex fix.');
        }
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            $this->markTestSkipped('Vibe interpreter not available.');
        }

        $kernel = $this->makeKernel();
        $registry = new BridgeRegistry();
        $registry->register(new VibeBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle('greet');
        $output = $kernel->lastOutput();

        $this->assertStringContainsString('Vibe ready.', $output);
    }

    private function makeKernel(): VibeTestKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $displayDriver = new VibeNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), new KeyInputManager());

        return new VibeTestKernel(
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

    private function shouldSkipMixRegistry(): bool
    {
        $tagRegistry = __DIR__ . '/../../vendor/bluefission/develation/src/Parsing/Registry/TagRegistry.php';
        $mixRegistry = __DIR__ . '/../../vendor/bluefission/vibrato/src/Vibrato/Parsing/Mix/MixRegistry.php';

        if (!is_file($tagRegistry) || !is_file($mixRegistry)) {
            return false;
        }

        $tagContents = file_get_contents($tagRegistry);
        $mixContents = file_get_contents($mixRegistry);
        if ($tagContents === false || $mixContents === false) {
            return false;
        }

        return str_contains($tagContents, '(?P<{$tag}>') && str_contains($mixContents, 'mix:');
    }
}

final class VibeTestKernel extends Kernel
{
    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class VibeNullDisplayDriver implements IDisplayDriver
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
