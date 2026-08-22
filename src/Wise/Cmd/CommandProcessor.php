<?php
namespace BlueFission\Wise\Cmd;


use BlueFission\Arr;
use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\SyntaxTreeWalker;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Data\Storage\Storage;
use BlueFission\Automata\LLM\Prompts\ConsoleResponse;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Services\Application as App;
use BlueFission\Wise\Nav\INavigator;
use BlueFission\Str;
use BlueFission\Val;

class CommandProcessor implements ICommandProcessor
{
    protected const CRASH_ALERT_INTERVAL = 5;

    protected $_parser;
    protected $_app;
    protected $_storage;
    protected $_grammar;
    protected $_llmClient;
    protected $_name;
    protected $_navigator;
    protected ?Command $_lastCommand = null;
    protected ?string $_lastResource = null;
    protected bool $_confirmationRequired = false;

    protected $_availableCommands = [];

    protected $_responseWords = [
        'yes' => ['y', 'yes', 'yeah', 'yep', 'ya', 'right', 'true', 'uh huh', 'sure', 'ok', 'okay', 'affirmative'],
        'no' => ['n', 'no', 'nope', 'nah', 'wrong', 'false', 'negative'],
    ];

    protected $_keywords = [];

    public function __construct(Storage $storage, IClient $llmClient = null, INavigator $navigator = null)
    {
        $this->setKeywords();
        $this->_parser = new CommandParser();
        $this->_app = App::instance();
        $this->_storage = $storage;
        $this->_storage->activate();

        $this->_llmClient = $llmClient;
        $this->_navigator = $navigator;

        $this->_name = $this->_app->name();

        $this->_grammar = $this->_app->getDynamicInstance(Grammar::class);

        $this->_storage->read();
        // Instantiate the storage object values
        $this->_storage->history = $this->_storage->history ?? [];
        $this->_storage->log = $this->_storage->log ?? [];
        $this->_storage->errors = $this->_storage->errors ?? 0;
        $this->_storage->warnings = $this->_storage->warnings ?? 0;
        $this->_storage->crashes = $this->_storage->crashes ?? 0;
        $this->_storage->write();
        $this->_availableCommands = $this->populateAvailableCommands();
    }

    public function availableCommands()
    {
        return $this->_availableCommands;
    }

    public function process(CommandRequest|Command|array|string $request): CommandResult
    {
        $this->_lastCommand = null;
        $this->_lastResource = null;
        $this->_confirmationRequired = false;

        $request = $request instanceof CommandRequest ? $request : new CommandRequest($request);
        $input = $request->input();

        try {
            if ($request->isContinuation()) {
                return $this->resume($request);
            }

            if (Str::is($input)) {
                if (Str::isEmpty(Str::trim($input))) {
                    return CommandResult::invalid('No command entered.', ['input_empty'], $request->context());
                }

                if (!$request->shouldExecute()) {
                    $command = $this->_parser->parse(Str::trim($input));
                    if (!$command instanceof Command || !$command->complete()) {
                        return CommandResult::invalid('Command could not be resolved.', ['command_incomplete'], $request->context());
                    }
                    $this->rememberCommand($command);
                    return CommandResult::parsed($command, $request->context());
                }

                $output = $this->handle($input);
                return $this->resultForOutput($output, $request->context());
            }

            $command = $input instanceof Command ? $input : $this->convertToCommand($input);
            if (!$command->complete()) {
                return CommandResult::invalid('Command could not be resolved.', ['command_incomplete'], $request->context());
            }

            $this->rememberCommand($command);
            if (!$request->shouldExecute()) {
                return CommandResult::parsed($command, $request->context());
            }

            if (!$this->resourceExists((string)$this->_lastResource)) {
                return CommandResult::invalid(
                    'Resource not found.',
                    ['resource_not_found'],
                    $request->context(),
                    $command
                );
            }

            $output = $this->executeCommand($command);
            return $this->resultForOutput($output, $request->context());
        } catch (\Throwable $exception) {
            return CommandResult::failure('Command processing failed.', [
                'exception' => $exception::class,
            ], $request->context());
        }
    }

    public function handle($input)
    {
        $this->addToLog($input, 'input');
        if (empty($input)) {
            $output = "No command entered.";
            $this->addToLog($output, 'output');
            return $output;
        }


        $input = trim($input);

        if (!empty($this->_storage->confirmCmd)) {
            $this->_confirmationRequired = false;
            if (in_array(strtolower($input), $this->_responseWords['yes'], true)) {
                $command = $this->convertToCommand($this->_storage->confirmCmd);
                unset($this->_storage->currentCmd);

                $accept = new Command();
                $accept->args[] = $input;

                $this->addToHistory($accept);

                $input = "";
            } elseif (in_array(strtolower($input), $this->_responseWords['no'], true)) {
                $deny = new Command();
                $deny->args[] = $input;

                $this->addToHistory($deny);
                
                $output = "Command cancelled.";
                $this->addToLog($output, 'output');
                unset($this->_storage->confirmCmd);
                $this->_storage->write();
                return $output;
            }
            unset($this->_storage->confirmCmd);
            $this->_storage->write();
        }

        if ($this->_storage->currentCmd) {

            $command = $this->convertToCommand($this->_storage->currentCmd);


            $newCommand = $this->_parser->parse($input);

            if ($newCommand->verb == 'cancel') {
                unset($this->_storage->currentCmd);
                $this->_storage->write();
                $output = "Command cancelled.";
                $this->addToLog($output, 'output');
                return $output;
            }

            if ($newCommand->verb && !empty($newCommand->resources)) {
                $command = $newCommand;
            }

            if (empty($command->resources)) {
                if (empty($newCommand->resources)) {
                    $command->resources[] = $input;
                } else {
                    $command->resources = $newCommand->resources;
                }

            } else if (empty($command->verb)) {
                if (empty($newCommand->verb)) {
                    $command->verb = $input;
                } else {
                    $command->verb = $newCommand->verb;
                }
            }

            unset($this->_storage->currentCmd);
        } elseif (!isset($command)) {
            $context = [
                'app' => 'app',
                'last_resource' => $this->_storage->lastResource,
                'last_set' => $this->_storage->lastSet,
            ];

            $inputWithPronouns = $this->_parser->processPronouns($input, $context);
            $inputForParse = $inputWithPronouns ?: $input;

            $command = $this->_parser->parse($inputForParse);
        }

        if (empty($command->resources) && $command->verb == 'help') {
            $output = "How can I help? Type `list all resources` to see what you have access to.";
            $this->addToLog($output, 'output');
            return $output;
        }

        if (!$command->verb && empty($command->resources)) {
            $command = $this->_parser->processQuestion($inputForParse ?? $input);
        }

        if (!$command || (empty($command->verb) && empty($command->resources))) {
            $error = $this->suggestCommands($input, $command);
            if ((empty($command->verb) && empty($command->resources))) {
                $this->addToLog($error, 'output');
                return $error;
            }
        }

        if (empty($command->verb)) {
            $this->_storage->currentCmd = $command;
            $this->_storage->write();
            $output = "What should I do with {$command->resources[0]}? [list, search, or help]";

            $this->addToLog($output, 'output');
            return $output;
        }

        if (empty($command->resources)) {
            $this->_storage->currentCmd = $command;
            $this->_storage->write();
            $output = $this->suggestResources($command);
            $this->addToLog($output, 'output');
            return $output;
        }

        $this->rememberCommand($command);

        if (!$this->resourceExists($command->resources[0])) {
            if ($this->isSameCommand($command, 4)) {
                unset($this->_storage->currentCmd);
                $this->_storage->confirmCmd = $command;
                $this->_confirmationRequired = true;
                $this->_storage->write();
                $output = "You've submitted this command over 3 times in a row. Are you sure you want to run it again? [yes/no]";
                $this->addToLog($output, 'output');
                return $output;
            }

            $this->_storage->lastResource = $command->resources[0];
            $this->_storage->lastVerb = $command->verb;
            $this->_storage->write();

            if ($this->shouldUseNavigator($input)) {
                $response = $this->respondWithNavigator($input) ?? $this->respond($input);
                if ($response) {
                    $this->addToLog($response, 'output');
                    return $response;
                }
            }

            $output = 'Resource not found';
            $this->addToLog($output, 'output');
            return $output;
        }

        unset($this->_storage->currentCmd);
        $this->_storage->write();

        $output = $this->executeCommand($command);
        $this->addToLog($output, 'output');
        return $output;
    }

    protected function executeCommand(Command $command)
    {
        $this->rememberCommand($command);
        // Check if the same command is submitted 3 times in a row
        if ($this->isSameCommand($command, 4)) {
            unset($this->_storage->currentCmd);
            $this->_storage->confirmCmd = $command;
            $this->_confirmationRequired = true;
            $this->_storage->write();
            return "You've submitted this command over 3 times in a row. Are you sure you want to run it again? [yes/no]";
        }

        $service = $command->resources[0];
        $behavior = $command->verb;
        $data = $command->args;

        $this->_storage->lastResource = $command->resources[0];
        $this->_storage->lastVerb = $command->verb;
        $this->_storage->write();

        try {
            $result = $this->_app->command($service, $behavior, $data, function ($output) {
                return $output ?: $this->conversationalResponse("Command triggered with empty response. Perhaps it failed?");
            });

        } catch (\Throwable $exception) {
            $this->_storage->crashes++;
            $this->_storage->write();

            $crashCount = (int)$this->_storage->crashes;
            $operatorAlert = $crashCount % static::CRASH_ALERT_INTERVAL === 0;

            return CommandResult::failure(
                $operatorAlert
                    ? 'Repeated command failures detected. Re-assess the command or run diagnostics.'
                    : 'Command execution failed.',
                ['exception' => $exception::class],
                [
                    'crash_count' => $crashCount,
                    'crash_alert_interval' => static::CRASH_ALERT_INTERVAL,
                    'operator_alert' => $operatorAlert,
                ]
            );
        }

        // Check for warnings
        if (empty($result)) {
            $this->_storage->warnings++;
            $this->_storage->write();

            // Check for warnings multiple of 5
            if ($this->_storage->warnings % 5 == 0) {
                return $this->conversationalResponse("Your commands have produced 5 warnings. Do you need help (`list all resources`)?");
            }
        }

        return $result ?: "No response. Command failed.";
    }

    protected function convertToCommand($object)
    {
        $command = new Command();

        if (Str::is($object)) {
            return $this->_parser->parse(Str::trim($object));
        }

        if (Arr::is($object)) {
            $verb = $object['verb'] ?? $object['operator'] ?? $object['behavior'] ?? null;
            $resources = $object['resources'] ?? $object['objects'] ?? $object['resource'] ?? $object['object'] ?? [];
            $args = $object['args'] ?? $object['values'] ?? $object['literals'] ?? [];

            $command->verb = Val::is($verb) ? (string)$verb : null;
            $command->resources = Arr::is($resources) ? $resources : [$resources];
            $command->args = Arr::is($args) ? $args : [$args];

            return $command;
        }

        if (isset($object->verb)) {
            $command->verb = $object->verb;
        }

        if (isset($object->resources)) {
            $command->resources = $object->resources;
        }

        if (isset($object->args)) {
            $command->args = $object->args;
        }

        return $command;
    }

    protected function populateAvailableCommands()
    {
        $availableCommands = [];
        $abilities = $this->_app->getAbilities();

        foreach ($abilities as $service => $commands) {
            foreach ($commands as $command) {
                $availableCommands[] = "$command $service";
            }
        }

        return $availableCommands;
    }

    public function lastCommand()
    {
        return $this->_lastCommand ?? $this->_storage->currentCmd ?? null;
    }

    public function lastResource()
    {
        return $this->_lastResource ?? $this->_storage->lastResource ?? null;
    }

    private function rememberCommand(Command $command): void
    {
        $this->_lastCommand = $command;
        $resource = Arr::isNotEmpty($command->resources) ? $command->resources[0] : null;
        $this->_lastResource = Val::isNotEmpty($resource) ? (string)$resource : null;
    }

    private function resultForOutput(mixed $output, array $metadata = []): CommandResult
    {
        if ($output instanceof CommandResult) {
            return $output->withMetadata($metadata);
        }

        if ($this->_confirmationRequired || Val::is($this->_storage->confirmCmd ?? null)) {
            return CommandResult::pending(
                $output,
                $this->_lastCommand,
                $this->continuationToken(),
                $metadata
            );
        }

        return CommandResult::completed($output, $this->_lastCommand, $metadata);
    }

    private function continuationToken(): string
    {
        $token = $this->_storage->confirmToken ?? null;
        if (Str::isNotEmpty((string)$token)) {
            return (string)$token;
        }

        $token = Str::uuid4();
        $this->_storage->confirmToken = $token;
        $this->_storage->write();

        return $token;
    }

    private function resume(CommandRequest $request): CommandResult
    {
        $token = (string)$request->continuationToken();
        $consumed = Arr::is($this->_storage->consumedContinuations ?? null)
            ? $this->_storage->consumedContinuations
            : [];

        if (Arr::has($consumed, $token, true)) {
            return CommandResult::invalid(
                'Command continuation has already been consumed.',
                ['continuation_consumed'],
                $request->context()
            );
        }

        $expected = (string)($this->_storage->confirmToken ?? '');
        if (Str::isEmpty($expected) || !Str::match($expected, $token)) {
            return CommandResult::invalid(
                'Command continuation is invalid.',
                ['continuation_invalid'],
                $request->context()
            );
        }

        $consumed[] = $token;
        $consumed = Arr::make($consumed)->slice(-50)->toArray();
        $this->_storage->consumedContinuations = $consumed;
        unset($this->_storage->confirmToken);
        $this->_storage->write();

        $output = $this->handle($request->approved() ? 'yes' : 'no');
        return $this->resultForOutput($output, $request->context());
    }

    public function suggestCommands($input, &$cmd)
    {
        $bestMatch = '';
        $highestSimilarity = 0;

        foreach ($this->_availableCommands as $command) {
            $similarity = 0;
            similar_text($input, $command, $similarity);

            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestMatch = $command;
            }
        }

        if ($highestSimilarity > 75) {
            $this->_storage->confirmCmd = $bestMatch;
            $this->_confirmationRequired = true;
            unset($this->_storage->currentCmd);
            $this->_storage->write();
            $args = '';
            if ($cmd && isset($cmd->args) && is_array($cmd->args) && count($cmd->args) > 0) {
                 $args = " {$cmd->args[0]}";
            }

            $this->_storage->warnings++;
            $this->_storage->write();
            return "Did you mean '{$bestMatch}{$args}'? [yes/no]";
        }

        $error = $this->parseAsStatement($input, $cmd);
        if (is_string($error) && str_contains($error, 'Unknown nonterminal')) {
            $error = null;
        }

        $response = $this->respondWithNavigator($input) ?? $this->respond($input);
        
        if (!$response) {
            $this->_storage->errors++;
            $this->_storage->write();

            // Check for errors multiple of 5
            if ($this->_storage->errors % 5 == 0) {
                return $this->conversationalResponse("You've had 5 errors. Have you reviewed your command list (`list all commands`)?");
            }
        }

        $response = $response ?? $error;

        $response = $response ?? $this->conversationalResponse("I did not understand your command.");

        return $response ?? "I did not understand your command.";
    }

    private function parseAsStatement($input, &$cmd)
    {
        $statement = strtolower($input);
        $tree = [];
        try {
            $tokens = $this->_grammar->tokenize($statement);
            $tree = $this->_grammar->parse($tokens);
        } catch (\Exception $e) {
            // return false;
            return $e->getMessage();
        }

        $walker = new SyntaxTreeWalker($tree);
        $results = $walker->walk();

        if (!$cmd) {
            $cmd = new Command();
        }

        $cmd->verb = $results['operator'];
        $cmd->resources = $results['objects'];

        $extractor = new EntityExtractor();
        $methods = [
            'literals',
            'name',
            'values',
            'object',
            'tags',
            'mentions',
            'number',
            'web',
            'email',
            'date',
            'time',
            'hex',
            'adverb',
            'operation'
        ];

        $entities = [];

        foreach ($methods as $method) {
            $entities[$method] = $extractor->$method($input);
            if (empty($cmd->args)) {
                foreach ($entities[$method] as $entity) {
                    $cmd->args[] = $entity;
                }
            }
        }
    }

    private function isSameCommand(Command $command, $times)
    {
        $this->addToHistory($command);
        $history = $this->_storage->history ?? [];

        $count = count($history);
        if ($count < $times) {
            return false;
        }

        // Check if the last $times commands are the same
        for ($i = $count - $times + 1; $i < $count; $i++) {
            if (serialize($this->convertToCommand($history[$i])) != serialize($this->convertToCommand($history[$i - 1]))) {
                return false;
            }
        }

        return true;
    }

    private function addToLog($text, $direction = 'input')
    {
        $log = $this->_storage->log ?? [];

        $direction = strtolower($direction) == 'input' ? 'input' : 'output';

        // Add the current command to the log
        $log[] = ['direction'=>$direction, 'text'=>$text];
        
        if (Arr::count($log) > 50) {
            $log = Arr::make($log);
            $log->shift();
            $log = $log->val();
        }
        $this->_storage->log = $log;
        $this->_storage->write();
    }

    private function addToHistory($command) 
    {
        $history = $this->_storage->history ?? [];

        // Add the current command to the history
        $history[] = $command;
        if (Arr::count($history) > 50) {
            $history = Arr::make($history);
            $history->shift();
            $history = $history->val();
        }
        $this->_storage->history = $history;
        $this->_storage->write();
    }

    private function respond($input)
    {
        $input = strtolower($input);
        $bestMatch = '';
        $highestSimilarity = 0;

        foreach ($this->_keywords as $topic) {
            foreach ($topic['keywords'] as $keyword) {
                $similarity = 0;
                similar_text($input, $keyword, $similarity);

                if ($similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $bestMatch = $topic;
                }
            }
        }

        if ($highestSimilarity > 50) {
            $responseIndex = array_rand($bestMatch['responses']);
            $response = $bestMatch['responses'][$responseIndex];
            if (is_callable($response)) {
                return $response();
            }
            return $response;
        }

        return null;
    }

    private function respondWithNavigator(string $input): ?string
    {
        if (!$this->_navigator) {
            return null;
        }

        $response = $this->_navigator->process($input);
        return $response !== '' ? $response : null;
    }

    private function suggestResources($command)
    {
        switch( $command->verb ) {
            case 'find':
            case 'search':
                return 'No registered resource specified in the command. Which resource do you want to search? [files, web, or variables]';
            case 'list':
                return 'No registered resource specified in the command. Which resource do you want to list? [files, todos, or variables]';
            default:
                return "No registered resource specified in the command. Which resource do you want to {$command->verb} using?";
        }
    }

    private function resourceExists(string $resource): bool
    {
        $abilities = $this->_app->getAbilities();
        return Arr::hasKey($abilities, $resource);
    }

    private function shouldUseNavigator(string $input): bool
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return false;
        }

        if (str_ends_with($trimmed, '?')) {
            return true;
        }

        return (bool)preg_match('/^(who|what|where|when|why|how)\\b/i', $trimmed);
    }

    private function conversationalResponse( $input )
    {
        if ( ! $this->_llmClient ) {
            return $input;
        }

        $log = $this->_storage->log ?? [];
        $history = "";
        foreach ($log as $line) {
            if ( is_array($line) ) {
                if (isset($line['direction']) && $line['direction'] == 'input') {
                    $history .= "[Console User]: ".$line['text']."\n";
                } else {
                    $history .= "[System]:  ".$line['text']."\n";
                }
            }
        }
        $verbs = implode(', ', $this->_parser->getSystemVerbs());
        $resources = implode(', ', $this->_parser->getSystemResources());
        $prepositions = implode(', ', $this->_parser->getSystemPrepositions());

        // Generate the code using an LLM
        $prompt = (new ConsoleResponse($input, $history, $verbs, $resources, $prepositions))->prompt();
        $llmResponse = $this->_llmClient->complete($prompt, ['max_tokens'=>150, 'stop'=>["\n"]]);

        // Check for errors in the response
        if (!$llmResponse->success()) {
            $output = "{$input} Additionally, there was an error generating a suggestion.";
            return $output;
        }

        // Get the generated code
        $output = $llmResponse->messages()->first();

        if ($input) {
            $output = $output;
        }

        return $input . ' ' . $output;
    }

    private function setKeywords()
    {
    
        $this->_keywords = [
        'greetings' => [
            'keywords' => ['hi', 'hello', 'hey', 'good morning', 'good evening', 'yo'],
            'responses' => ['Hello!', 'Hi!', 'Nice to see you'],
        ],
        'identity' => [
            'keywords' => ['who am i', 'what is my name'],
            'responses' => ['You are '. $this->_name .', the user of this command prompt interpreter.'],
        ],
        'who_are_you' => [
            'keywords' => ['who are you', 'your name', 'what is your name', 'identify yourself'],
            'responses' => ['I am System, your command prompt interpreter.']
        ],
        'wellbeing' => [
            'keywords' => ['how are you', 'how is it going', 'what\'s up', 'how do you feel'],
            'responses' => [function () {
            $os = PHP_OS_FAMILY;

            // Get the number of CPU cores
            $numCores = 0;
            if ($os === 'Linux') {
                $numCores = (int)shell_exec("grep -P '^processor' /proc/cpuinfo | wc -l");
            } elseif ($os === 'Windows') {
                $numCores = (int)shell_exec("wmic cpu get NumberOfCores /Value | findstr /R /C:\"^NumberOfCores=[0-9]*\" | findstr /R /C:\"[0-9]*\"");
            }

            if ( ! function_exists('sys_getloadavg') ) {
                return 'I\'m not sure how I\'m feeling right now. I cannot retrieve system load information.';
            }

            // Get the load average
            $load = \sys_getloadavg();

            if ($numCores > 0 && is_array($load) && count($load) > 0) {
                $loadPercentage = ($load[0] / $numCores) * 100;

                if ($loadPercentage <= 33) {
                    return 'I\'m feeling great! The system is running smoothly with a low load.';
                } elseif ($loadPercentage <= 66) {
                    return 'I\'m doing okay. The system is experiencing a moderate load, but it\'s manageable.';
                } else {
                    return 'I\'m feeling a bit stressed. The system is under a high load at the moment.';
                }
            } else {
                return 'I\'m not sure how I\'m feeling right now. I cannot retrieve system load information.';
            }
        },
        'I\'m doing well, thank you!', 'I\'m just a program, but I\'m functioning as expected.'],
        ],
        'farewell' => [
            'keywords' => ['goodbye', 'bye', 'see you', 'good night'],
            'responses' => ['Goodbye!', 'See you later!', 'Have a great day!'],
        ],
        'help' => [
            'keywords' => ['help', 'can you help', 'assist', 'aid'],
            'responses' => ['How can I help you?', 'What do you need assistance with?']
        ],
        'time' => [
            'keywords' => ['what time is it', 'current time', 'tell me the time'],
            'responses' => ['The current time is ' . date('h:i A') . '.']
        ],
        'date' => [
            'keywords' => ['what is the date', 'current date', 'tell me the date', 'what day is it'],
            'responses' => ['Today is ' . date('Y-m-d') . '.']
        ],
        'weather' => [
            'keywords' => ['what is the weather', 'weather outside', 'how is the weather'],
            'responses' => ['I am not capable of providing weather information. Please try a different service.']
        ],
        'joke' => [
            'keywords' => ['tell me a joke', 'joke', 'make me laugh'],
            'responses' => [
                'Why did the chicken go to the seance? To get to the other side.',
                'Why don\'t scientists trust atoms? Because they make up everything.',
                'I would tell you a joke about UDP, but I\'m not sure if you would get it.'
            ]
        ],
        'compliment' => [
            'keywords' => ['say something nice', 'compliment', 'tell me something good'],
            'responses' => ['You\'re doing a great job!', 'You\'re a fantastic problem solver!', 'Your perseverance is inspiring!']
        ],
        'calculator' => [
            'keywords' => ['calculator', 'calculation', 'compute', 'calculate'],
            'responses' => ['Please provide the expression you\'d like to calculate.']
        ],
        'commands' => [
            'keywords' => ['list commands', 'available commands', 'commands', 'what can you do'],
            'responses' => ['Here are some commands I can understand: help, time, date, calc, ...']
        ],
        'error' => [
            'keywords' => ['error', 'issue', 'problem', 'bug', 'fix'],
            'responses' => ['Please describe the error or issue you\'re facing, and I\'ll do my best to help.']
        ],
        'weather' => [
            'keywords' => ['what is the weather', 'weather outside', 'how is the weather'],
            'responses' => ['I am not capable of providing weather information. Please try a different service or skill.']
        ],
        'file_system' => [
            'keywords' => ['file', 'directory', 'folder', 'path', 'create', 'delete', 'rename'],
            'responses' => ['Please specify the file operation you\'d like to perform (e.g., create, delete, rename).']
        ],
        'database' => [
            'keywords' => ['database', 'query', 'sql', 'select', 'insert', 'update', 'delete'],
            'responses' => ['Please provide the SQL query you\'d like to execute.']
        ],
        'network' => [
            'keywords' => ['network', 'ip address', 'ping', 'traceroute', 'port'],
            'responses' => ['Please provide more information about the network operation you\'d like to perform.']
        ],
        'search' => [
            'keywords' => ['search', 'find', 'lookup', 'query'],
            'responses' => ['Please provide more information about the search you\'d like to perform.']
        ],
        'system_info' => [
            'keywords' => ['system info', 'system information', 'php info', 'php version'],
            'responses' => ['PHP Version: ' . phpversion() . ', Operating System: ' . PHP_OS . '.']
        ],
        'server_name' => [
            'keywords' => ['server name', 'server', 'hostname', 'where am i'],
            'responses' => [
                (isset($_SERVER['SERVER_NAME']) ? 'Server Name: ' . $_SERVER['SERVER_NAME'] . '.' : 'Server Name information is not available in this environment.')
            ],
        ],
        'server_address' => [
            'keywords' => ['server address', 'ip address', 'server ip'],
            'responses' => [
                (isset($_SERVER['SERVER_ADDR']) ? 'Server IP Address: ' . $_SERVER['SERVER_ADDR'] . '.' : 'Server IP Address information is not available in this environment.')
            ],
        ],
        'client_address' => [
            'keywords' => ['my ip', 'my ip address', 'client ip', 'client address'],
            'responses' => [
                (isset($_SERVER['REMOTE_ADDR']) ? 'Your IP Address: ' . $_SERVER['REMOTE_ADDR'] . '.' : 'Client IP Address information is not available in this environment.')
            ],
        ],
        'uptime' => [
            'keywords' => ['uptime', 'system uptime', 'server uptime'],
            'responses' => [
                (isset($_SERVER['REQUEST_TIME']) ? 'System Uptime: ' . round((time() - $_SERVER['REQUEST_TIME']) / 86400, 2) . ' days.' : 'System Uptime information is not available in this environment.')
            ],
        ],
        'memory_usage' => [
            'keywords' => ['memory usage', 'memory', 'ram', 'memory used'],
            'responses' => ['Current Memory Usage: ' . round(memory_get_usage() / 1024 / 1024, 2) . ' MB.']
        ],
        'disk_space' => [
            'keywords' => ['disk space', 'free space', 'disk', 'storage'],
            'responses' => ['Free Disk Space: ' . round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . ' GB.']
        ],
        'php_extensions' => [
            'keywords' => ['php extensions', 'extensions', 'modules', 'php modules'],
            'responses' => ['PHP Extensions: ' . implode(', ', get_loaded_extensions()) . '.']
        ],
        'current_directory' => [
            'keywords' => ['current directory', 'pwd', 'working directory', 'dir'],
            'responses' => ['Current Working Directory: ' . getcwd() . '.']
        ],
        'permissions' => [
            'keywords' => ['permissions', 'user permissions', 'access', 'what can i do'],
            'responses' => [
                'As an LLM, you can access various system information and issue commands. To see a list of available commands, type `list all commands` or `help`.'
            ],
        ],
        'abilities' => [
            'keywords' => ['abilities', 'capabilities', 'skills'],
            'responses' => [
                'As an LLM, you can process natural language, understand context, and perform tasks based on your commands. As a System, I give you access to several tools and functions. To get help with available commands, type `help` or `list all commands`.'
            ],
        ],
        'creator' => [
            'keywords' => ['creator', 'who made you', 'who created you', 'who built you', 'who is your designer', 'architecture'],
            'responses' => [
                'I am an interactive command terminal developed by BlueFission. My purpose is to assist you in serving users and communicating with the outside world through commands.'
            ],
        ],
        'support' => [
            'keywords' => ['support', 'help', 'assistance', 'help me please', 'trouble'],
            'responses' => [
                'If you need help or support, contact your adminstrator or reach out to our creators at BlueFission.'
            ],
        ]
    ];
    }
}
