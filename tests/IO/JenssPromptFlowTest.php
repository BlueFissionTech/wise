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
use BlueFission\Wise\Sys\IO\CommandInputStream;
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

final class JenssPromptFlowTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->resetKernelInstance();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-jenss-flow-' . uniqid();
        mkdir($this->root . DIRECTORY_SEPARATOR . 'cmd', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    /**
     * @dataProvider promptScriptProvider
     */
    public function testPromptScriptsRenderSummaries(string $scriptName, array $responses, array $expectedLines): void
    {
        if (!class_exists(\BlueFission\Jenerator\Parsing\JenssParser::class)
            && !class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)) {
            $this->markTestSkipped('JenSS interpreter not available.');
        }

        $source = file_get_contents(__DIR__ . '/../../examples/root/cmd/' . $scriptName . '.jss');
        file_put_contents(
            $this->root . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . $scriptName . '.jss',
            $source !== false ? $source : ''
        );

        $kernel = $this->makeKernel($responses);
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $kernel->setBridgeRegistry($registry);

        $kernel->handle($scriptName);
        $output = $kernel->lastOutput();

        foreach ($expectedLines as $line) {
            $this->assertStringContainsString($line, $output);
        }
    }

    public static function promptScriptProvider(): array
    {
        return [
            'setup-network' => [
                'setup-network',
                ['80,443', '22', '8.8.8.8', ''],
                [
                    'Network setup.',
                    'Network summary:',
                    'Allow: 80,443',
                    'Block: 22',
                    'DNS: 8.8.8.8',
                    'Proxy: ',
                ],
            ],
            'setup-profile' => [
                'setup-profile',
                ['Neutral', 'calm', 'builder'],
                [
                    'Chatbot profile setup.',
                    'Profile summary:',
                    'Tone: Neutral',
                    'Persona: calm',
                    'Reminders: builder',
                ],
            ],
        ];
    }

    private function makeKernel(array $responses): JenssFlowKernel
    {
        $processManager = new ProcessManager();
        $memoryManager = new MemoryManager(300, 60);
        $fileSystem = new FileSystemManager(['root' => $this->root]);
        $interpreter = new Interpreter(new Grammar(new StemmerLemmatizer(), []), new Documenter(), new Walker());
        $inputStream = new CommandInputStream($responses, false);
        $keyInput = new KeyInputManager($inputStream, true);
        $displayDriver = new JenssFlowNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), $keyInput);
        $console->registerInputChannel('system');

        return new JenssFlowKernel(
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

final class JenssFlowKernel extends Kernel
{
    public function lastOutput(): string
    {
        return (string)$this->_output;
    }
}

final class JenssFlowNullDisplayDriver implements IDisplayDriver
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
