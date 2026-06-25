#!/usr/bin/php
<?php

declare(strict_types=1);

use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Exe\VibeBridge;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Wise\Sys\FileSystemManager;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

final class ExampleFixtureLlmClient implements IClient
{
    private array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $responses = Arr::make($this->responses);
        $response = (string)($responses->shift() ?? 'generated fixture response');
        $this->responses = $responses->val();
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

$exampleRoot = __DIR__ . DIRECTORY_SEPARATOR . 'root';
$registry = new BridgeRegistry();
$registry->register(new JenssBridge());
$registry->register(new VibeBridge());

$results = [];
$failures = 0;

foreach (exampleFiles($exampleRoot) as $path) {
    $relativePath = relativePath($exampleRoot, $path);
    $bridge = $registry->bridgeForFile($path);

    if ($bridge === null) {
        continue;
    }

    if ($bridge instanceof JenssBridge && !jenssAvailable()) {
        $results[] = ['skip', $relativePath, 'JenSS interpreter is unavailable'];
        continue;
    }

    if ($bridge instanceof VibeBridge && (!vibeAvailable() || shouldSkipVibeMixRegistry())) {
        $results[] = ['skip', $relativePath, 'Vibe interpreter is unavailable or blocked by registry compatibility'];
        continue;
    }

    $context = new BridgeContext(
        null,
        null,
        $_ENV,
        [$exampleRoot],
        [$exampleRoot],
        varsForExample($relativePath),
        null,
        promptResponder(),
        null,
        new ExampleFixtureLlmClient(generatedResponsesForExample($relativePath))
    );

    try {
        $result = $bridge->runFile($path, $context);
    } catch (\Throwable $exception) {
        $failures++;
        $results[] = ['fail', $relativePath, $exception->getMessage()];
        continue;
    }

    if ($result->successFlag()) {
        $results[] = ['ok', $relativePath, $bridge->name()];
        continue;
    }

    $failures++;
    $results[] = ['fail', $relativePath, Str::trim($result->output())];
}

foreach ($results as [$status, $path, $detail]) {
    echo '[' . $status . '] ' . $path . ' - ' . $detail . PHP_EOL;
}

exit($failures === 0 ? 0 : 1);

/**
 * @return array<int, string>
 */
function exampleFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $extension = Str::lower($file->getExtension());
        if (!Arr::has(['jss', 'vibe'], $extension, true)) {
            continue;
        }

        $files[] = $file->getPathname();
    }

    return Arr::make($files)->sort()->val();
}

function relativePath(string $root, string $path): string
{
    $relative = Str::sub($path, Str::len($root) + 1);
    return Str::replace($relative, DIRECTORY_SEPARATOR, '/');
}

function jenssAvailable(): bool
{
    return class_exists(\BlueFission\Jenerator\Parsing\JenssParser::class)
        || class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class);
}

function vibeAvailable(): bool
{
    return class_exists(\BlueFission\Vibrato\Reader::class);
}

function shouldSkipVibeMixRegistry(): bool
{
    $tagRegistry = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'bluefission'
        . DIRECTORY_SEPARATOR . 'develation'
        . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . 'Parsing'
        . DIRECTORY_SEPARATOR . 'Registry'
        . DIRECTORY_SEPARATOR . 'TagRegistry.php';
    $mixRegistry = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'bluefission'
        . DIRECTORY_SEPARATOR . 'vibrato'
        . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . 'Vibrato'
        . DIRECTORY_SEPARATOR . 'Parsing'
        . DIRECTORY_SEPARATOR . 'Mix'
        . DIRECTORY_SEPARATOR . 'MixRegistry.php';

    if (!FileSystemManager::pathExists($tagRegistry) || !FileSystemManager::pathExists($mixRegistry)) {
        return false;
    }

    $tagContents = FileSystemManager::readPath($tagRegistry);
    $mixContents = FileSystemManager::readPath($mixRegistry);
    if ($tagContents === '' || $mixContents === '') {
        return false;
    }

    return Str::has($tagContents, '(?P<{$tag}>') && Str::has($mixContents, 'mix:');
}

/**
 * @return callable(string): string
 */
function promptResponder(): callable
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
        $queue = Arr::make($responses);
        $response = $queue->shift() ?? 'example';
        $responses = $queue->val();

        return $response;
    };
}

/**
 * @return array<string, mixed>
 */
function varsForExample(string $relativePath): array
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

/**
 * @return array<int, string>
 */
function generatedResponsesForExample(string $relativePath): array
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
