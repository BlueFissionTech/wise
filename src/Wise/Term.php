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
use BlueFission\Async\Heap;
use BlueFission\Data\Storage\Disk;
use BlueFission\Automata\Language\{
	Interpreter,
	Grammar,
	StemmerLemmatizer,
	Documenter,
	Walker
};
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Connections\Stdio;

require '../../vendor/autoload.php';

$stdio = (new Stdio())->open();
ConsoleDisplayUtil::init($stdio);
KeyInputUtil::init($stdio);

// Create and initialize the kernel
$kernel = new Kernel(
    new ProcessManager(),
    new CommandProcessor(new Disk() ),
    new MemoryManager(300, 60),  // MemoryManager with 300 seconds threshold and 60 seconds monitoring interval
    new FileSystemManager(),
    new Interpreter( new Grammar( new StemmerLemmatizer() ), new Documenter(), new Walker() ),
    new DisplayManager( new ConsoleDisplayDriver ),
    new KeyInputManager()
);

// Set the Async handler to Heap
$kernel->setAsyncHandler(Heap::class);

// Boot the kernel
$kernel->boot();

// Handle a request
$kernel->run();