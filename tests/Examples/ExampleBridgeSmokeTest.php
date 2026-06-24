<?php

namespace BlueFission\Tests\Examples;

use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Exe\VibeBridge;
use PHPUnit\Framework\TestCase;

final class ExampleBridgeSmokeTest extends TestCase
{
    private string $exampleRoot;

    protected function setUp(): void
    {
        $this->exampleRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'root';
    }

    public function testBatchCommandExampleDocumentsCurrentScripts(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'batch-commands.txt';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('hello', $contents);
        $this->assertStringContainsString('status', $contents);
        $this->assertStringContainsString('resource-event', $contents);
        $this->assertStringContainsString('exit', $contents);
    }

    /**
     * @dataProvider bridgeExampleProvider
     */
    public function testExamplesDeclareARegisteredBridge(string $relativePath): void
    {
        $registry = $this->registry();
        $path = $this->exampleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $this->assertNotNull($registry->bridgeForFile($path), $relativePath);
    }

    /**
     * @dataProvider jenssExampleProvider
     */
    public function testJenssExamplesExecuteThroughBridge(string $relativePath): void
    {
        $this->requireJenss();

        $bridge = new JenssBridge();
        $path = $this->exampleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $context = new BridgeContext(
            null,
            null,
            [],
            [$this->exampleRoot],
            [$this->exampleRoot],
            [],
            null,
            $this->promptResponder()
        );

        $result = $bridge->runFile($path, $context);

        $this->assertTrue($result->successFlag(), $relativePath . ': ' . $result->output());
        $this->assertSame($path, $result->meta()['path'] ?? null);
    }

    /**
     * @dataProvider vibeExampleProvider
     */
    public function testVibeExamplesExecuteThroughBridge(string $relativePath): void
    {
        if ($this->shouldSkipVibeMixRegistry()) {
            $this->markTestSkipped('Vibe mix tags require TagRegistry regex fix.');
        }
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            $this->markTestSkipped('Vibe interpreter not available.');
        }

        $bridge = new VibeBridge();
        $path = $this->exampleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $context = new BridgeContext(
            null,
            null,
            [],
            [$this->exampleRoot],
            [$this->exampleRoot],
            $this->varsForExample($relativePath),
            null,
            null,
            null,
            new ExampleFixtureLlmClient($this->generatedResponsesForExample($relativePath))
        );

        $result = $bridge->runFile($path, $context);

        $this->assertTrue($result->successFlag(), $relativePath . ': ' . $result->output());
        $this->assertSame($path, $result->meta()['path'] ?? null);

        $vars = $result->meta()['variables'] ?? [];
        if ($relativePath === 'cmd/onboard.vibe') {
            $this->assertSame('Example User', $vars['name'] ?? null);
            $this->assertSame('engineering', $vars['focus'] ?? null);
            $this->assertStringContainsString('Next action:', $result->output());
        }
        if ($relativePath === 'cmd/strategy.vibe') {
            $this->assertSame('example modernization', $vars['topic'] ?? null);
            $this->assertSame('review', $vars['mode'] ?? null);
            $this->assertStringContainsString('Plan:', $result->output());
        }
    }

    public static function bridgeExampleProvider(): array
    {
        return self::exampleProvider(['jss', 'vibe']);
    }

    public static function jenssExampleProvider(): array
    {
        return self::exampleProvider(['jss']);
    }

    public static function vibeExampleProvider(): array
    {
        return self::exampleProvider(['vibe']);
    }

    private static function exampleProvider(array $extensions): array
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'root';
        $cases = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $cases[$relativePath] = [$relativePath];
        }

        ksort($cases);
        return $cases;
    }

    private function registry(): BridgeRegistry
    {
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $registry->register(new VibeBridge());

        return $registry;
    }

    private function requireJenss(): void
    {
        if (!class_exists(\BlueFission\Jenerator\Parsing\JenssParser::class)
            && !class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)) {
            $this->markTestSkipped('JenSS interpreter not available.');
        }
    }

    private function shouldSkipVibeMixRegistry(): bool
    {
        $tagRegistry = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'bluefission'
            . DIRECTORY_SEPARATOR . 'develation'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Parsing'
            . DIRECTORY_SEPARATOR . 'Registry'
            . DIRECTORY_SEPARATOR . 'TagRegistry.php';
        $mixRegistry = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'bluefission'
            . DIRECTORY_SEPARATOR . 'vibrato'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Vibrato'
            . DIRECTORY_SEPARATOR . 'Parsing'
            . DIRECTORY_SEPARATOR . 'Mix'
            . DIRECTORY_SEPARATOR . 'MixRegistry.php';

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

    private function promptResponder(): callable
    {
        $responses = [
            'console-user',
            'operator',
            'engineering',
            'keep example scripts deterministic',
            '80,443',
            '22',
            '8.8.8.8',
            '',
            'neutral',
            'systems',
            'medium',
            'yes',
            'no',
        ];

        return function (string $prompt) use (&$responses): string {
            return array_shift($responses) ?? 'example';
        };
    }

    private function varsForExample(string $relativePath): array
    {
        $common = [
            'name' => 'Example User',
            'role' => 'operator',
            'focus' => 'engineering',
            'tone' => 'neutral',
            'goal' => 'verify Wise examples',
            'resource' => 'none',
            'nextAction' => 'run the focused smoke tests',
            'topic' => 'example modernization',
            'mode' => 'review',
            'context' => 'local deterministic fixture',
            'constraints' => 'no network and no credentials',
            'resources' => 'JenSS, Vibe, bridge registry',
            'outcome' => 'examples remain executable',
            'plan' => "1. Run bridge smoke\n2. Run batch terminal\n3. Run PHPUnit",
        ];

        if ($relativePath === 'sys/res/message.vibe') {
            return $common + [
                'action' => 'list',
                'recipient' => 'console',
                'content' => 'Example message',
                'detail' => 'Example detail',
                'entries' => ['message-001', 'message-002'],
            ];
        }

        return $common;
    }

    private function generatedResponsesForExample(string $relativePath): array
    {
        return match ($relativePath) {
            'cmd/onboard.vibe' => [
                'Example User',
                'operator',
                'engineering',
                'neutral',
                'verify Wise examples',
                'none',
                'run the focused smoke tests',
            ],
            'cmd/strategy.vibe' => [
                'example modernization',
                'review',
                'local deterministic fixture',
                'no network and no credentials',
                'JenSS, Vibe, bridge registry',
                'examples remain executable',
                "1. Run bridge smoke\n2. Run batch terminal\n3. Run PHPUnit",
                'run the focused smoke tests',
            ],
            default => [],
        };
    }
}

final class ExampleFixtureLlmClient implements IClient
{
    private array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $response = (string)(array_shift($this->responses) ?? 'generated fixture response');
        if ($callback) {
            $callback($response);
        }

        $reply = new Reply();
        $reply->addMessage($response);

        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        return $this->generate($input, $config);
    }

    public function respond($input, $config = []): Reply
    {
        return $this->generate($input, $config);
    }
}
