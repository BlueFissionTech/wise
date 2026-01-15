<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\{
    MemoryManager,
    FileSystemManager
};
use BlueFission\Wise\Usr\Identity;
use BlueFission\Wise\Cmd\{CommandHandler, CommandProcessor};
use BlueFission\Wise\Cli\Console;
use BlueFission\Automata\Language\IInterpreter;
use BlueFission\Automata\Language\Reader;
use BlueFission\Async\Async;
use BlueFission\Data\Storage\Storage;
use BlueFission\Services\Application as App;
use BlueFission\Services\Authenticator as Auth;
use BlueFission\Collections\Collection;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\IPC\IPC;
use BlueFission\Str;
use BlueFission\Wise\Usr\Profile;
use BlueFission\Wise\Sys\Memory\WorkingMemoryCoordinator;

class Kernel {
    use Traits\ManagesFileSystem;
    use Traits\ReceivesMessages;

    protected $_identity;
    protected $_processManager;
    protected $_commandProcessor;
    protected $_commandHandler;
    protected $_memoryManager;
    protected $_fileSystemManager;
    protected $_sessionStorage;
    protected $_dataStorage;
    protected $_interpreter;
    protected $_ipc;

    protected $_console;

    protected ?WorkingMemoryCoordinator $_workingMemory = null;
    protected ?Profile $_profile = null;
    protected ?Profile $_systemProfile = null;

    protected $_async;
    protected $_queue;
    protected $_output;

    protected static $_instance = null;

    const INPUT_CHANNEL = '__input__';

    public function __construct(ProcessManager $processManager, CommandProcessor $commandProcessor, MemoryManager $memoryManager, FileSystemManager $fileSystemManager, IInterpreter $interpreter, Console $console, Storage $sessionStorage, Storage $dataStorage, IPC $ipc) {
        if (self::$_instance) {
            return self::$_instance;
        }

        $this->_processManager = $processManager;
        $this->_commandProcessor = $commandProcessor;
        $this->_memoryManager = $memoryManager;
        $this->_fileSystemManager = $fileSystemManager;
        $this->_interpreter = $interpreter;
        $this->_console = $console;
        $this->_sessionStorage = $sessionStorage;
        $this->_dataStorage = $dataStorage;
        $this->_ipc = $ipc;

        $this->_output = '';

        $this->_commandHandler = new CommandHandler($this);
        $this->_identity = new Identity($this, new Auth( $this->_sessionStorage, $this->_dataStorage ));
        $this->_profile = $this->_identity->profile();
        $this->_systemProfile = new Profile('system', ['system']);

        self::$_instance = $this;
    }

    public static function instance() {
        return self::$_instance;
    }

    public function setAsyncHandler(string $class) {
        $this->_async = $class;
    }

    public function setQueueHandler(string $class) {
        $this->_queue = $class;
    }

    public function boot() {
        $this->_memoryManager->setAsyncHandler($this->_async);
        $this->_processManager->setMemoryManager($this->_memoryManager);

        // Initialize kernel components
        $this->_processManager->initialize();
        $this->_fileSystemManager->initialize();
        $this->_memoryManager->initialize();

        $this->_fileSystemManager->open();
    }

    public function enqueue($channel, $message) {
        $this->_queue::enqueue($channel, $message);
    }

    public function dequeue($channel) {
        return $this->_queue::dequeue($channel);
    }

    public function setMessage($channel, $message) {
        $this->_ipc->write($channel, $message);
    }

    public function getMessage($channel) {
        return $this->_ipc->read($channel);
    }

    public function handle($request)
    {
        $request = trim($request);
        // this method shoud determine if a request should be handled by the shell interpreter, the command processor, or directly by the kernel's internal commands

        $this->recordMemory($request);

        // Determine if request is a command or a script
        if ($this->_commandHandler->canHandle($request)) {
            $this->handleNative($request);
        } elseif ($this->_interpreter->isValid($request)) {
            $this->handleScript($request);
        } else {
            $this->handleCommand($request);
        }
    }

    public function setWorkingMemory(WorkingMemoryCoordinator $workingMemory): void
    {
        $this->_workingMemory = $workingMemory;
    }

    public function hasWorkingMemory(): bool
    {
        return $this->_workingMemory !== null;
    }

    public function setProfile(Profile $profile): void
    {
        $this->_profile = $profile;
    }

    public function profile(): ?Profile
    {
        return $this->_profile;
    }

    public function setWorkingMemoryMaxSize(string $scope, ?int $size, ?string $ownerId = null): void
    {
        if (!$this->_workingMemory) {
            return;
        }

        $this->_workingMemory->setPartitionMaxSize($scope, $size, $ownerId);
    }

    public function workingMemoryMaxSize(string $scope, ?string $ownerId = null): ?int
    {
        if (!$this->_workingMemory) {
            return null;
        }

        $owner = $ownerId ?? $this->_profile?->id();
        return $this->_workingMemory->partitionMaxSize($scope, $owner);
    }

    private function recordMemory(string $request): void
    {
        if (!$this->_workingMemory || !$this->_profile || $request === '') {
            return;
        }

        $episodeId = 'console:' . bin2hex(random_bytes(6));
        $this->_workingMemory->record('user', $request, $episodeId, $this->_profile, $this->_profile->id());
    }

    private function recordMemoryOutput(string $output): void
    {
        if (!$this->_workingMemory || !$this->_systemProfile || $output === '') {
            return;
        }

        $episodeId = 'console:' . bin2hex(random_bytes(6));
        $this->_workingMemory->record('global', $output, $episodeId, $this->_systemProfile, $this->_systemProfile->id());
    }

    private function execute($request)
    {
        $response = $this->_commandProcessor->handle($request);
        $command = $this->_commandProcessor->lastCommand();
        $resource = $this->_commandProcessor->lastResource();

        if (! $command || ! $resource) {
            return $response ?? "Invalid command.";
        }

        $resourceObj = App::instance()->resolve($resource);

        if (! $resourceObj) {
            return "Resource not found.";
        }

        $process = $this->_processManager->createProcess($resourceObj, $command);

        return $response;
    }

    public function handleScript($request) {
        $this->display("Not Implemented");
    }

    public function runAsync( $task ){
        // Call the do method statically
        return $this->_async::do($task);
    }

    private function handleCommand($request) {
        // Dispatch request to appropriate service or process
        try {
            $response = $this->execute($request);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->recordMemoryOutput((string)$response);
        $this->_output = $response;
    }

    private function handleCommandAsync($request) {
        // Dispatch request to appropriate service or process
        $this->_async::do((function($resolve, $reject) use ($request) {
            try {
                $response = $this->_commandProcessor->process($request);
                $resolve($response);
            } catch (\Exception $e) {
                $response = $e->getMessage();
                $reject($response);
            }
        })->bindTo($this))->then((function($response) {
            $this->display($response);
        })->bindTo($this), function($error) {
            // Handle this somehow
        })->try();
    }

    private function handleNative($request) {
        $output = $this->_commandHandler->handle($request);
        $this->_console->output($output, 'system');
        $this->recordMemoryOutput((string)$output);
    }

    public function run() {
        $this->_console->clear();

        // Register input channels
        $this->_console->registerInputChannel('stdio'); // Keyboard input
        $this->_console->registerInputChannel('system'); // System command responses
        $this->_console->registerInputChannel('message'); // Background messages

        // Non-blocking command processing handler for the console
        $this->_console->when(Event::PROCESSED, (function($b, $m) {
            $this->handle($m->data[0] ?? '');
            $this->_console->output($this->_output, 'system');
            $this->_output = '';
        })->bindTo($this, $this));

        while (true) {
            $this->repl(); // Read, Evaluate, Print, Loop
            usleep(50000); // Sleep for 0.05 seconds
        }
    }

    public function repl() {
        $this->_console
            ->display()
            ->listen();
    }

    public function shutdown() {
        // Shutdown kernel components
    }

    private function maybeLogin() {
        // Check if user is logged in
        if ($this->_identity->isAuthenticated()) {
            return;
        }

        // If not, prompt for login
        $this->_identity->prompt();

        // temporarily allow logins
        return;

        if (!$this->_identity->isAuthenticated()) {
            exit(0);
        }
    }

    protected function output() {
        return $this->_output;
    }
}
