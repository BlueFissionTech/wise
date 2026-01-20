<?php

namespace BlueFission\Tests\IO;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\VibeBridge;
use BlueFission\Wise\Sys\FileSystemManager;
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

final class MessageResourcePipelineTest extends TestCase
{
    private string $root;
    private string $inputFile;
    private string $storagePath;
    private string|false $prevStoragePath;
    private string|false $prevStorageName;
    private string|false $prevSessionId;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        require_once __DIR__ . '/../../src/Wise/Support/store.php';

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-msg-' . uniqid();
        $resourceDir = $this->root . DIRECTORY_SEPARATOR . 'sys' . DIRECTORY_SEPARATOR . 'res';
        mkdir($resourceDir, 0777, true);

        $source = file_get_contents(__DIR__ . '/../../examples/root/sys/res/message.vibe');
        file_put_contents($resourceDir . DIRECTORY_SEPARATOR . 'message.vibe', $source !== false ? $source : '');

        $this->inputFile = $this->root . DIRECTORY_SEPARATOR . 'input.txt';
        file_put_contents($this->inputFile, "send new message to user \"hello!\"\nlist all messages\n");

        $this->storagePath = $this->root . DIRECTORY_SEPARATOR . 'storage';
        mkdir($this->storagePath, 0777, true);

        $this->prevStoragePath = getenv('STORAGE_PATH');
        $this->prevStorageName = getenv('STORAGE_FILE_NAME');
        $this->prevSessionId = getenv('CLI_SESSION_ID');

        putenv('STORAGE_PATH=' . $this->storagePath);
        putenv('STORAGE_FILE_NAME=wise_test_storage.json');
        putenv('CLI_SESSION_ID=wise-test-' . uniqid());
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('STORAGE_PATH', $this->prevStoragePath);
        $this->restoreEnv('STORAGE_FILE_NAME', $this->prevStorageName);
        $this->restoreEnv('CLI_SESSION_ID', $this->prevSessionId);
        $this->deleteStorageArtifacts();
        gc_collect_cycles();
        $this->removeDir($this->root);
    }

    public function testVibeMessageResourceRespondsToPipedCommands(): void
    {
        $kernel = $this->makeKernel();
        $registry = new BridgeRegistry();
        $registry->register(new VibeBridge());
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

        $this->assertCount(2, $outputs);
        $this->assertStringContainsString('Message queued to user: hello!', $outputs[0]);
        $this->assertStringContainsString('List of messages:', $outputs[1]);
        $this->assertStringContainsString('(to user): hello!', $outputs[1]);
    }

    private function makeKernel(): MessageTestKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $displayDriver = new MessageNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), new KeyInputManager());

        return new MessageTestKernel(
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

    private function restoreEnv(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            return;
        }

        putenv($key . '=' . $value);
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

    private function deleteStorageArtifacts(): void
    {
        $storageFile = $this->storagePath . DIRECTORY_SEPARATOR . 'wise_test_storage.json';
        if (is_file($storageFile)) {
            @unlink($storageFile);
        }
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

final class MessageTestKernel extends Kernel
{
    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class MessageNullDisplayDriver implements IDisplayDriver
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
