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
use BlueFission\Async\{Shell, Fork};
use BlueFission\Data\Storage\Disk;
use BlueFission\Data\Storage\SQLite;
use BlueFission\Automata\Language\{
	Interpreter,
	Grammar,
	StemmerLemmatizer,
	Documenter,
	Walker
};
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Wise\Sys\Con\ExtendedStdio;// as Stdio;

require '../../vendor/autoload.php';

mb_internal_encoding("UTF-8");

$stdio = (new ExtendedStdio('polling.php'))->open();
ConsoleDisplayUtil::init($stdio);
KeyInputUtil::init($stdio);

// Create and initialize the kernel
$kernel = new Kernel(
    new ProcessManager(),
    new CommandProcessor( new Disk() ),
    new MemoryManager(300, 60),  // MemoryManager with 300 seconds threshold and 60 seconds monitoring interval
    new FileSystemManager(['root'=>getcwd()]),
    new Interpreter( new Grammar( new StemmerLemmatizer() ), new Documenter(), new Walker() ),
    new DisplayManager( new ConsoleDisplayDriver ),
    new KeyInputManager(),
    new Disk(['location'=>'../', 'name'=>'storage.json']),
    new SQLite(['database'=>'database.db'])
);

// Set the Async handler to Heap
if (function_exists('pcntl_fork')) {
	// If Forking is available
	$kernel->setAsyncHandler(Fork::class);
} else {
	$kernel->setAsyncHandler(Shell::class);
}
	
// Boot the kernel
$kernel->boot();

// Handle a request
$kernel->run();