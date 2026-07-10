#!/usr/bin/php
<?php

namespace BlueFission\Wise;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Sys\{
	MemoryManager,
	FileSystemManager,
	DirectoryManager,
	DisplayManager,
	KeyInputManager,
	Drivers\ConsoleDisplayDriver,
	Drivers\BufferDisplayDriver,
	Drivers\StreamDisplayDriver,
	Drivers\CompositeDisplayDriver,
	Utl\ConsoleDisplayUtil,
	Utl\KeyInputUtil
};
use BlueFission\Wise\Sys\IO\CommandInputStream;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Nav\SynthetiqBootstrap;
use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cli\Components;
use BlueFission\Wise\Sys\Memory\WorkingMemoryCoordinator;
use BlueFission\Wise\Sys\Memory\SynthetiqMemoryAdapter;
use BlueFission\Wise\Usr\Profile;
use BlueFission\Wise\Exe\{BridgeRegistry, JenssBridge, VibeBridge};
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;
use BlueFission\Cli\Util\Tty;
use BlueFission\Cli\Util\ProgressBar;
use BlueFission\Cli\Util\StatusBar;
use BlueFission\Async\{Heap, Thread, Fork};
use BlueFission\Data\FileSystem;
use BlueFission\Data\Storage\{Disk, Memory, SQLite};
use BlueFission\Automata\Language\{
	Interpreter,
	Grammar,
	StemmerLemmatizer,
	Documenter,
	Reader,
	Walker
};
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Wise\Sys\Conn\ExtendedStdio;
use BlueFission\IPC\IPC;
use BlueFission\Data\Queues\MemQueue;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/src/Wise/Support/store.php';

$virtualRoot = getenv('WISE_FS_ROOT') ?: ($rootPath . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'root');
$sessionLocation = $rootPath . DIRECTORY_SEPARATOR . 'artifacts';
$sessionDirectory = new FileSystem(['root' => $rootPath, 'filter' => []]);
if (!$sessionDirectory->exists($sessionLocation)) {
    $sessionDirectory->mkdir('artifacts');
}
$storageLocation = $sessionLocation . DIRECTORY_SEPARATOR . 'storage';
if (!$sessionDirectory->exists($storageLocation)) {
    $sessionDirectory->mkdir('artifacts' . DIRECTORY_SEPARATOR . 'storage');
}
if (getenv('STORAGE_PATH') === false) {
    putenv('STORAGE_PATH=' . $storageLocation);
}
if (getenv('STORAGE_FILE_NAME') === false) {
    putenv('STORAGE_FILE_NAME=wise_cli_storage.json');
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

mb_internal_encoding("UTF-8");

MemQueue::setMode(MemQueue::FIFO);
Thread::setBootstrap($rootPath . '/vendor/autoload.php');

$argv = $_SERVER['argv'] ?? [];
$argParser = new Args(['allowUnknown' => true, 'autoHelp' => true]);
$argParser->addOptions([
    new OptionDefinition('fs-root', [
        'short' => ['r'],
        'type' => 'string',
        'env' => 'WISE_FS_ROOT',
        'description' => 'Root directory for the Wise virtual filesystem.',
    ]),
    new OptionDefinition('input-file', [
        'short' => ['i'],
        'type' => 'string',
        'env' => 'WISE_INPUT_FILE',
        'description' => 'Read commands from a file.',
    ]),
    new OptionDefinition('input-exit', [
        'type' => 'bool',
        'env' => 'WISE_INPUT_EXIT',
        'description' => 'Exit after input stream completes.',
    ]),
    new OptionDefinition('display-mode', [
        'type' => 'string',
        'env' => 'WISE_DISPLAY_MODE',
        'description' => 'Display mode: static or dynamic.',
    ]),
    new OptionDefinition('output-mode', [
        'type' => 'string',
        'env' => 'WISE_OUTPUT_MODE',
        'description' => 'Output mode: console, file, or buffer.',
    ]),
    new OptionDefinition('output-file', [
        'type' => 'string',
        'env' => 'WISE_OUTPUT_FILE',
        'description' => 'Path for file output.',
    ]),
    new OptionDefinition('output-append', [
        'type' => 'bool',
        'env' => 'WISE_OUTPUT_APPEND',
        'description' => 'Append to output file instead of truncating.',
    ]),
    new OptionDefinition('output-targets', [
        'type' => 'string',
        'env' => 'WISE_OUTPUT_TARGETS',
        'description' => 'Comma-separated output targets.',
    ]),
    new OptionDefinition('stdin-blocking', [
        'type' => 'bool',
        'env' => 'WISE_STDIN_BLOCKING',
        'description' => 'Force blocking input reads.',
    ]),
    new OptionDefinition('tty-echo', [
        'type' => 'bool',
        'env' => 'WISE_TTY_ECHO',
        'description' => 'Enable terminal echo while capturing input.',
    ]),
    new OptionDefinition('nav-boot', [
        'type' => 'bool',
        'env' => 'WISE_NAV_BOOT',
        'description' => 'Force navigator boot in batch mode.',
    ]),
    new OptionDefinition('profile', [
        'type' => 'string',
        'env' => 'WISE_PROFILE_ID',
        'description' => 'Profile identifier for the session.',
    ]),
    new OptionDefinition('memory-max-global', [
        'type' => 'int',
        'env' => 'WISE_MEMORY_MAX_GLOBAL',
        'description' => 'Global working memory cap.',
    ]),
    new OptionDefinition('memory-max-user', [
        'type' => 'int',
        'env' => 'WISE_MEMORY_MAX_USER',
        'description' => 'User working memory cap.',
    ]),
]);
$argParser->parse($argv);
$options = $argParser->options();
if (!empty($options['help'])) {
    echo $argParser->usage($argv[0] ?? 'terminal.php') . PHP_EOL;
    exit(0);
}

$envMap = [
    'fs-root' => 'WISE_FS_ROOT',
    'input-file' => 'WISE_INPUT_FILE',
    'input-exit' => 'WISE_INPUT_EXIT',
    'display-mode' => 'WISE_DISPLAY_MODE',
    'output-mode' => 'WISE_OUTPUT_MODE',
    'output-file' => 'WISE_OUTPUT_FILE',
    'output-append' => 'WISE_OUTPUT_APPEND',
    'output-targets' => 'WISE_OUTPUT_TARGETS',
    'stdin-blocking' => 'WISE_STDIN_BLOCKING',
    'tty-echo' => 'WISE_TTY_ECHO',
    'nav-boot' => 'WISE_NAV_BOOT',
    'profile' => 'WISE_PROFILE_ID',
    'memory-max-global' => 'WISE_MEMORY_MAX_GLOBAL',
    'memory-max-user' => 'WISE_MEMORY_MAX_USER',
];
foreach ($envMap as $option => $envKey) {
    if (!array_key_exists($option, $options)) {
        continue;
    }
    $value = $options[$option];
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    putenv($envKey . '=' . $value);
}

$inputFile = getenv('WISE_INPUT_FILE');
$inputStream = $inputFile ? CommandInputStream::fromFile($inputFile) : null;
$batchMode = $inputStream !== null;

$displayMode = getenv('WISE_DISPLAY_MODE');
$displayMode = $displayMode ? Str::make($displayMode)->trim()->lower()->val() : ($batchMode ? 'static' : 'dynamic');
if (!$batchMode && $displayMode === 'dynamic' && !Tty::isTty(STDOUT)) {
    $displayMode = 'static';
}
if (PHP_OS === 'WINNT' && !$batchMode && $displayMode === 'dynamic') {
    if (getenv('WISE_STDIN_PROXY') === false) {
        putenv('WISE_STDIN_PROXY=0');
    }
    if (getenv('WISE_STDIN_BLOCKING') === false) {
        putenv('WISE_STDIN_BLOCKING=0');
    }
}
if (PHP_OS === 'Linux' && !$batchMode && getenv('WISE_STDIN_BLOCKING') === false && $displayMode === 'dynamic') {
    putenv('WISE_STDIN_BLOCKING=0');
}
if (!$batchMode && getenv('WISE_TTY_ECHO') === false && $displayMode === 'dynamic' && PHP_OS === 'Linux') {
    putenv('WISE_TTY_ECHO=0');
}

$exitOnEnd = getenv('WISE_INPUT_EXIT');
$exitOnEnd = $exitOnEnd === false
    ? ($inputStream !== null)
    : filter_var($exitOnEnd, FILTER_VALIDATE_BOOLEAN);
$keyInputManager = new KeyInputManager($inputStream, $exitOnEnd);

$outputMode = getenv('WISE_OUTPUT_MODE');
$outputFile = getenv('WISE_OUTPUT_FILE');
$outputAppend = filter_var(getenv('WISE_OUTPUT_APPEND') ?: '0', FILTER_VALIDATE_BOOLEAN);
$outputTargets = getenv('WISE_OUTPUT_TARGETS');
$displayDriver = null;
if ($outputTargets !== false && Str::trim($outputTargets) !== '') {
    $targets = [];
    foreach (explode(',', $outputTargets) as $target) {
        $target = Str::trim($target);
        if ($target !== '') {
            $targets[] = $target;
        }
    }
    $drivers = [];

    foreach ($targets as $target) {
        $target = Str::lower($target);
        if ($target === 'buffer') {
            $drivers[] = new BufferDisplayDriver();
            continue;
        }
        if ($target === 'stdout') {
            $drivers[] = new StreamDisplayDriver('php://stdout', true);
            continue;
        }
        if ($target === 'console') {
            if ($batchMode) {
                $drivers[] = new StreamDisplayDriver('php://stdout', true);
            } else {
                $drivers[] = new ConsoleDisplayDriver();
            }
            continue;
        }
        if (Str::startsWith($target, 'file:')) {
            $path = Str::make($target)->sub(Str::len('file:'))->trim()->val();
            $path = $path !== '' ? $path : ($outputFile ?: 'wise_output.txt');
            $drivers[] = new StreamDisplayDriver($path, $outputAppend);
        }
    }

    if (Arr::count($drivers) > 1) {
        $displayDriver = new CompositeDisplayDriver($drivers);
    } elseif (Arr::count($drivers) === 1) {
        $displayDriver = $drivers[0];
    }
}
if ($outputMode === 'buffer') {
    $displayDriver = new BufferDisplayDriver();
} elseif ($outputMode === 'file' || ($outputFile && $outputMode !== 'console')) {
    $displayDriver = new StreamDisplayDriver($outputFile ?: 'wise_output.txt', $outputAppend);
} elseif ($batchMode) {
    $displayDriver = new StreamDisplayDriver('php://stdout', true);
} else {
    $displayDriver = new ConsoleDisplayDriver();
}

if (!$batchMode) {
    // Handle IO
    $stdio = (new ExtendedStdio('php ' . $rootPath . '/stdin.php', 'php ' . $rootPath . '/polling.php'))->open();
    ConsoleDisplayUtil::init($stdio);
    KeyInputUtil::init($stdio);
}

// Prepare the Console
$console = new Console(
    new DisplayManager($displayDriver),
    $keyInputManager
);
$console->setDisplayMode($displayMode === 'static' ? Console::STATIC_MODE : Console::DYNAMIC_MODE);
$console->registerInputChannel('stdio');
$console->registerInputChannel('system');
$console->registerInputChannel('message');
$dynamicDisplay = !$batchMode && $console->getDisplayMode() === Console::DYNAMIC_MODE;

$statusLine = null;
$screen = null;
$repl = null;

if (!$batchMode) {
    $splash = new Components\SplashScreen();
    $repl = new Components\REPL();
    $screen = new Components\Screen();
    $screen->addChild($repl);
    if ($dynamicDisplay) {
        $statusLine = new Components\StatusLine(0, 0, 1, 2, '', 10, true, 2);
        $screen->addChild($statusLine);
        $repl->suspendInput();
    }
    $console->addComponent($screen);
    if ($dynamicDisplay) {
        $console->clear();
    }
    $console->display();
}

$bootStages = [
    'configs' => 'Loading Synthetiq configs',
    'model' => 'Preparing topic model cache',
    'interpreter' => 'Initializing language interpreter',
    'analyzer' => 'Preparing intent analyzer',
    'routes' => 'Training intent routes',
    'finalize' => 'Finalizing navigator',
    'kernel' => 'Booting workspace',
];
$bootProgress = null;
if (!$batchMode) {
    $progressTotal = Arr::count($bootStages) * 100;
    $progressBar = new ProgressBar($progressTotal);
    $statusBar = new StatusBar();
    $seenStages = [];
    $bootProgress = function (string $stage, string $message, array $meta = []) use (
        $console,
        $progressBar,
        $statusBar,
        &$seenStages,
        $bootStages,
        $statusLine,
        $dynamicDisplay,
        $progressTotal
    ) {
        if (!array_key_exists($stage, $bootStages)) {
            return;
        }
        if (!isset($seenStages[$stage])) {
            $seenStages[$stage] = Arr::count($seenStages) + 1;
        }

        $index = $seenStages[$stage];
        $subPercent = 100;
        if (Val::is($meta['sub_current'] ?? null) && Val::is($meta['sub_total'] ?? null) && $meta['sub_total'] > 0) {
            $subPercent = (int)floor(($meta['sub_current'] / $meta['sub_total']) * 100);
        }
        $overallCurrent = (($index - 1) * 100) + max(1, $subPercent);
        $progressBar->setCurrent(min($overallCurrent, $progressTotal));
        $statusBar->set('step', $index . '/' . Arr::count($bootStages));
        $statusBar->set('stage', $bootStages[$stage]);
        if (Val::is($meta['sub_current'] ?? null) && Val::is($meta['sub_total'] ?? null) && $meta['sub_total'] > 0) {
            $statusBar->set('progress', $meta['sub_current'] . '/' . $meta['sub_total']);
        } else {
            $statusBar->remove('progress');
        }
        if (isset($meta['cache']) && $meta['cache'] === true) {
            $statusBar->set('cache', 'hit');
        } elseif (isset($meta['cache'])) {
            $statusBar->set('cache', 'miss');
        } else {
            $statusBar->remove('cache');
        }

        $line = $message . PHP_EOL . $progressBar->render() . ' ' . $statusBar->render();
        if ($dynamicDisplay && $statusLine) {
            $statusLine->setContent($line);
            $console->display();
            return;
        }

        $console->output($line, 'system');
        $console->display();
    };
}

$grammarRules = [];
$workingMemory = new WorkingMemoryCoordinator(
    new Reader(new Grammar(new StemmerLemmatizer(), $grammarRules), new Documenter())
);
$memoryAdapter = new SynthetiqMemoryAdapter($workingMemory, new Profile('system', ['system']));

$navigator = null;
if (!$batchMode) {
    $console->output('Loading W.I.S.E...', 'system');
    $console->display();
}

if (!$batchMode || getenv('WISE_NAV_BOOT') === '1') {
    try {
        $navigator = SynthetiqBootstrap::fromVendorSampleConfigs(null, null, $memoryAdapter, $bootProgress);
    } catch (\Throwable $e) {
        $navigator = null;
    }
}

$kernel = new Kernel(
    new ProcessManager(),
    new CommandProcessor(
        new Disk(['location' => $sessionLocation, 'name' => 'command-storage.json']),
        null,
        $navigator
    ),
    new MemoryManager(300, 60),
    new FileSystemManager(['root' => $virtualRoot]),
    new Interpreter(new Grammar(new StemmerLemmatizer(), $grammarRules), new Documenter(), new Walker()),
    $console,
    new Disk(['location' => $sessionLocation, 'name' => 'storage.json']),
    new SQLite(['database' => $rootPath . DIRECTORY_SEPARATOR . 'database.db']),
    new IPC(new Memory())
);

$bridgeRegistry = new BridgeRegistry();
$bridgeRegistry->register(new JenssBridge());
$bridgeRegistry->register(new VibeBridge());
$kernel->setBridgeRegistry($bridgeRegistry);

$kernel->setBatchMode($batchMode);

$kernel->setWorkingMemory($workingMemory);
$kernel->setProfile(new Profile(getenv('WISE_PROFILE_ID') ?: 'console', ['user']));

$globalMax = getenv('WISE_MEMORY_MAX_GLOBAL');
if ($globalMax !== false && Num::is($globalMax)) {
    $kernel->setWorkingMemoryMaxSize('global', (int)$globalMax);
}

$userMax = getenv('WISE_MEMORY_MAX_USER');
if ($userMax !== false && Num::is($userMax)) {
    $kernel->setWorkingMemoryMaxSize('user', (int)$userMax, $kernel->profile()?->id());
}

// Set the Async handler to an appropriate driver
$kernel->setAsyncHandler($batchMode ? Heap::class : (function_exists('pcntl_fork') ? Fork::class : Thread::class));
$kernel->setQueueHandler( MemQueue::class );

// Boot the kernel
if ($bootProgress) {
    $bootProgress('kernel', 'Booting workspace...', ['phase' => 'kernel']);
} else {
    $console->output('Initializing workspace...', 'system');
    $console->display();
}
$kernel->boot();
if (!$batchMode && $statusLine && $screen && $repl) {
    $statusLine->setContent('');
    $screen->removeChild($statusLine);
    $repl->resumeInput();
    $console->display();
}
if (!$batchMode && $repl && isset($splash)) {
    $splashLines = $splash->draw();
    $splashText = Arr::is($splashLines) ? implode(PHP_EOL, $splashLines) : '';
    if ($splashText !== '') {
        $repl->addContent($splashText);
        $console->display();
    }
}
if (!$batchMode) {
    $console->output('Ready. Type `help` to get started.', 'system');
    $console->display();
}

if ($batchMode) {
    while (true) {
        $chunk = $keyInputManager->capture();
        if ($chunk === '') {
            break;
        }

        $command = Str::trim($chunk);
        if ($command === '') {
            continue;
        }
        if ($command === 'exit') {
            break;
        }

        $kernel->handle($command);
        $output = $kernel->consumeOutput();
        if ($output !== '') {
            $console->displayManager()->display($output . PHP_EOL);
            $console->displayManager()->print();
        }
    }
    exit(0);
}

// Handle a request
$kernel->run(false);
