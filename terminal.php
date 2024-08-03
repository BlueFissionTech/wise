#!/usr/bin/php
<?php

namespace BlueFission\Wise;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Sys\{
	MemoryManager,
	FileSystemManager,
	DisplayManager,
	KeyInputManager,
	Drivers\ConsoleDisplayDriver,
	Utl\ConsoleDisplayUtil,
	Utl\KeyInputUtil
};
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cli\Components;
use BlueFission\Async\{Heap, Thread, Fork};
use BlueFission\Data\Storage\{Disk, Memory, SQLite};
use BlueFission\Automata\Language\{
	Interpreter,
	Grammar,
	StemmerLemmatizer,
	Documenter,
	Walker
};
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Wise\Sys\Conn\ExtendedStdio;// as Stdio;
use BlueFission\IPC\IPC;
use BlueFission\Data\Queues\MemQueue;

require 'vendor/autoload.php';

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

mb_internal_encoding("UTF-8");

MemQueue::setMode(MemQueue::FIFO);

// Handle IO
$stdio = (new ExtendedStdio('php stdin.php', 'php polling.php'))->open();
ConsoleDisplayUtil::init($stdio);
KeyInputUtil::init($stdio);

// Prepare the Console
$splash = new Components\SplashScreen();

$repl = new Components\REPL($splash);

$screen = new Components\Screen();
$screen->addChild($repl);

$console = new Console(
    new DisplayManager( new ConsoleDisplayDriver ),
    new KeyInputManager()
);
$console->addComponent($screen);
$console->setDisplayMode(Console::STATIC_MODE);
// $console->setDisplayMode(Console::DYNAMIC_MODE);

$grammarRules = [];

// Create and initialize the kernel
$kernel = new Kernel(
    new ProcessManager(),
    new CommandProcessor( new Disk() ),
    new MemoryManager(300, 60),  // MemoryManager with 300 seconds threshold and 60 seconds monitoring interval
    new FileSystemManager(['root'=>getcwd()]),
    new Interpreter( new Grammar( new StemmerLemmatizer(), $grammarRules ), new Documenter(), new Walker() ),
    $console, // Our console object we previously setup
    new Disk(['location'=>'../', 'name'=>'storage.json']),
    new SQLite(['database'=>'database.db']),
    new IPC(new Memory())
);

// Set the Async handler to an appropriate driver
$kernel->setAsyncHandler( function_exists('pcntl_fork') ? Fork::class : Thread::class );
$kernel->setQueueHandler( MemQueue::class );
	
// Boot the kernel
$kernel->boot();

// Handle a request
$kernel->run();