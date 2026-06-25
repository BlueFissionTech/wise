<?php
// ResourceCommand.php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\DevElation as Dev;

class ResourceHelper extends Service
{
    protected $_page;
    protected $_perPage;
    protected string $_resourceName = 'resource';
    protected const OUTPUT_EVENT = 'wise.resource.output';
    protected const OUTPUT_REFRESH_EVENT = 'wise.resource.output.refresh';
    protected const WAITING_EVENT = 'wise.resource.waiting';
    protected array $_expectedOptions = [];
    protected array $_expectedOptionsMeta = [];
    protected array $_outputMeta = [];
    protected ?string $_lastOutputHash = null;
    protected ?string $_lastOptionsHash = null;
    protected int $_outputPreviewLines = 3;
    protected int $_outputPreviewChars = 240;
    protected int $_outputFullMax = 2000;

    public function __construct()
    {
        $this->_page = (int)store('_system.resource.page');
        $this->_perPage = (int)store('_system.resource.per_page');

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 25;

        $this->knownResources = array_keys(self::$resources);
        $this->resourceDescriptions = self::$resources;

        parent::__construct();
    }

    public function handle($behavior, $args)
    {
        $this->resetOutputTracking();
        $action = $behavior->name();

        if (count($args) > 1 && $args[0] === 'help') {
            $this->_response = $this->help();
            $this->setOutputType('help', [
                'commands' => ['list', 'previous', 'next', 'show', 'help'],
            ]);
            $this->emitOutputEventIfNeeded();
            $this->emitWaitingEventIfNeeded();
            return;
        }

        $command = isset($args[0]) ? $args[0] : '';

        switch ($action) {
            case 'list':
                $this->listResources($behavior, $args);
                break;
            case 'show':
                if (count($args) >= 1) {
                    $this->showResource($args[0]);
                } else {
                    $this->_response = "Please provide a resource name to show.";
                    $this->setOutputType('error', [
                        'error' => 'missing_name',
                        'action' => 'show',
                    ]);
                }
                break;
            case 'next':
                $this->next($behavior, $args);
                break;
            case 'previous':
                $this->previous($behavior, $args);
                break;
            case 'add':
                // Unimplemented method, to be added later
                break;
            case 'delete':
                // Unimplemented method, to be added later
                break;
            case 'help':
                $this->_response = $this->help();
                $this->setOutputType('help', [
                    'commands' => ['list', 'previous', 'next', 'show', 'help'],
                ]);
                break;
            default:
                if ($command == 'help') {
                    $this->_response = $this->help();
                    $this->setOutputType('help', [
                        'commands' => ['list', 'previous', 'next', 'show', 'help'],
                    ]);
                } else {
                    $this->_response = "Invalid action specified. Type 'help with resources' for available options.";
                    $this->setOutputType('error', [
                        'error' => 'invalid_action',
                        'action' => $action,
                    ]);
                }
        }

        $this->emitOutputEventIfNeeded();
        $this->emitWaitingEventIfNeeded();
    }

    public function showAll($behavior, $args)
    {
        $response = "";
        foreach ($this->resourceDescriptions as $resourceName=>$resource) {
            $response .= "Resource: " . $resourceName . "\n";
            $response .= "Description: " . $this->resourceDescriptions[$resourceName]['desc'] . "\n";
            $response .= "Hint: " . $this->resourceDescriptions[$resourceName]['hint'];
            $response .= "\n\n";
        }

        $this->_response = $response;
        $this->setOutputType('list', [
            'list_type' => 'resources',
            'items' => array_keys($this->resourceDescriptions),
            'count' => count($this->resourceDescriptions),
        ]);
    }

    private function listResources($behavior, $args)
    {
        $page = count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;
        }

        if ($this->_perPage < 1) {
            $this->_perPage = 25;
        }

        $resources = $this->knownResources;

        if ($resources !== null) {
            $total = count($resources);
            $totalPages = ceil($total / $this->_perPage);

            $pageStart = ($page - 1) * $this->_perPage;
            $pageEnd = $pageStart + $this->_perPage;

            $i = 0;
            $count = 0;
            $response = "";
            $pageItems = [];

            $response = "List of available resources:\n";
            foreach ($resources as $resource) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $resource . PHP_EOL;
                    $count++;
                    $pageItems[] = $resource;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} resources. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type 'show resource \"<resource>\"' for more information about a specific resource." . PHP_EOL;
            $response .= "Type `previous resources` or `next resources` to move through pages." . PHP_EOL;

            $this->setOutputType('list', [
                'list_type' => 'resources',
                'page' => $page,
                'per_page' => $this->_perPage,
                'total' => $total,
                'count' => $count,
                'items' => $pageItems,
            ]);
            $this->setExpectedOptions([
                'previous resources',
                'next resources',
                'help with resources',
                'show resource <resource>',
            ], ['source' => 'list']);
        } else {
            $response = "No resources have been set.";
            $this->setOutputType('list', [
                'list_type' => 'resources',
                'page' => $page,
                'per_page' => $this->_perPage,
                'total' => 0,
                'count' => 0,
                'items' => [],
            ]);
        }

        $this->_response = $response;
    }

    private function showResource($resourceName)
    {
        if (in_array($resourceName, $this->knownResources) && isset($this->resourceDescriptions[$resourceName])) {
            $this->_response = "Resource: " . $resourceName . "\n";
            $this->_response .= "Description: " . $this->resourceDescriptions[$resourceName]['desc'] . "\n";
            $this->_response .= "Hint: " . $this->resourceDescriptions[$resourceName]['hint'];
            $this->setOutputType('detail', [
                'name' => $resourceName,
                'entry' => $this->resourceDescriptions[$resourceName],
            ]);
        } else {
            $this->_response = "Resource '{$resourceName}' not found.";
            $this->setOutputType('error', [
                'error' => 'not_found',
                'action' => 'show',
                'name' => $resourceName,
            ]);
        }
    }

    public function next($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->listResources($behavior, $args);
    }

    public function previous($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->listResources($behavior, $args);
    }

    private function help(): string
    {
        return "Available commands for the Resource Manager:\n" .
            "- list all resources: List all available resources.\n" .
            "  \t\tUsage: list <number> resources, list <number> resources by <page>.\n" .
            "- previous resources: Scroll backwards through resource list.\n" .
            "- next resources: Scroll forward through resource list.\n" .
            "- show resource <resource>: Show the description and hint for a specific resource.\n" .
            "- help resource: Show this help message.";
    }

    protected function setOutputType(string $type, array $meta = []): void
    {
        $payload = array_merge([
            'output_type' => $type,
            'item' => $this->_resourceName,
        ], $meta);

        $this->mergeOutputMeta($payload);
    }

    protected function setOutputMeta(array $meta): void
    {
        $this->_outputMeta = $meta;
    }

    protected function mergeOutputMeta(array $meta): void
    {
        $this->_outputMeta = array_merge($this->_outputMeta, $meta);
    }

    protected function setExpectedOptions(array|string $options, array $meta = []): void
    {
        $options = is_array($options) ? $options : [$options];
        $normalized = [];
        foreach ($options as $option) {
            $option = trim((string)$option);
            if ($option === '') {
                continue;
            }
            $normalized[$option] = true;
        }

        $this->_expectedOptions = array_keys($normalized);
        $this->_expectedOptionsMeta = $meta;
    }

    protected function clearExpectedOptions(): void
    {
        $this->_expectedOptions = [];
        $this->_expectedOptionsMeta = [];
    }

    protected function resetOutputTracking(): void
    {
        $this->_outputMeta = [];
        $this->clearExpectedOptions();
    }

    protected function emitOutputEventIfNeeded(?string $output = null): void
    {
        $output = $output ?? $this->_response ?? '';
        if (!is_string($output) || $output === '') {
            return;
        }

        $hash = sha1($output);
        if ($hash === $this->_lastOutputHash) {
            $this->emitOutputRefreshEvent($output);
            return;
        }
        $this->_lastOutputHash = $hash;

        $preview = $this->buildOutputPreview($output);
        $markdown = Dev::apply('wise.resource.output.markdown', $preview['text']);

        $payload = array_merge([
            'resource' => $this->_resourceName,
            'output' => $preview['text'],
            'lines' => $preview['lines'],
            'truncated' => $preview['truncated'],
            'length' => strlen($output),
            'markdown' => $markdown,
            'hash' => $hash,
        ], $this->buildFullOutputMeta($output), $this->_outputMeta);

        $payload = Dev::apply('wise.resource.output.meta', $payload);

        $this->dispatch(self::OUTPUT_EVENT, new Meta(data: $payload, src: $this));
        $this->dispatch(Event::CHANGE, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.output.changed', $payload);
    }

    protected function emitOutputRefreshEvent(string $output): void
    {
        $preview = $this->buildOutputPreview($output);
        $payload = array_merge([
            'resource' => $this->_resourceName,
            'output' => $preview['text'],
            'lines' => $preview['lines'],
            'truncated' => $preview['truncated'],
            'length' => strlen($output),
        ], $this->buildFullOutputMeta($output), $this->_outputMeta);

        $payload = Dev::apply('wise.resource.output.refresh.meta', $payload);

        $this->dispatch(self::OUTPUT_REFRESH_EVENT, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.output.refresh', $payload);
    }

    protected function emitWaitingEventIfNeeded(?string $output = null): void
    {
        $output = $output ?? $this->_response ?? '';
        if (!is_string($output) || $output === '') {
            return;
        }

        $options = $this->_expectedOptions;
        if ($options === []) {
            return;
        }

        $hash = sha1(implode('|', $options) . '|' . $output);
        if ($hash === $this->_lastOptionsHash) {
            return;
        }
        $this->_lastOptionsHash = $hash;

        $payload = array_merge([
            'resource' => $this->_resourceName,
            'state' => 'waiting',
            'options' => $options,
        ], $this->_expectedOptionsMeta);

        $payload = Dev::apply('wise.resource.waiting.meta', $payload);

        $this->dispatch(self::WAITING_EVENT, new Meta(data: $payload, src: $this));
        $this->dispatch(Event::STATE_CHANGED, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.waiting', $payload);
    }

    protected function buildOutputPreview(string $output): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $output);
        $lines = preg_split('/\n/', $normalized);
        if (!is_array($lines) || $lines === []) {
            $lines = [$output];
        }

        $previewLines = array_slice($lines, 0, $this->_outputPreviewLines);
        $previewText = implode(PHP_EOL, $previewLines);
        $truncated = count($lines) > $this->_outputPreviewLines;

        if (strlen($previewText) > $this->_outputPreviewChars) {
            $previewText = substr($previewText, 0, $this->_outputPreviewChars);
            $truncated = true;
        }

        $previewText = Dev::apply('wise.resource.output.preview', $previewText);

        return [
            'text' => $previewText,
            'lines' => $previewLines,
            'truncated' => $truncated,
        ];
    }

    protected function buildFullOutputMeta(string $output): array
    {
        if (strlen($output) <= $this->_outputFullMax) {
            return ['full_output' => $output];
        }

        return [];
    }

    public static function addResource($resourceName, $description = '', $hint = '')
    {
        self::$resources[$resourceName] = [
            'desc' => $description,
            'hint' => $hint
        ];
    }

    public function __destruct()
    {
        store('_system.resource.page', $this->_page);
        store('_system.resource.per_page', $this->_perPage);
    }

    // Descriptions and hints for each resource
    private static $resources = [
        'system' => [
            'desc' => 'The system resource manages core system functionalities and configurations.',
            'hint' => 'Use the system resource in combination with other resources to create complex tasks or automate processes.'
        ],
        'model' => [
            'desc' => 'The model resource represents data structures and their relationships within the system.',
            'hint' => 'Combine model resources with the database and controller resources to create a fully functional application.'
        ],
        'controller' => [
            'desc' => 'The controller resource handles user input and manages the flow of data between the model and the view.',
            'hint' => 'Use controllers to create custom actions that leverage other resources like filemanager, database, or user.'
        ],
        'user' => [
            'desc' => 'The user resource manages user information, authentication, and authorization.',
            'hint' => 'Combine the user resource with the variable resource to store user-specific data and personalize the user experience.'
        ],
        'filemanager' => [
            'desc' => 'The filemanager resource provides tools for working with files and directories.',
            'hint' => 'Use the filemanager resource alongside the code resource to create or edit files programmatically.'
        ],
        'database' => [
            'desc' => 'The database resource manages data storage, retrieval, and manipulation.',
            'hint' => 'Combine the database resource with the model resource to build data-driven applications.'
        ],
        'code' => [
            'desc' => 'The code resource represents programming concepts, languages, and techniques.',
            'hint' => 'Use the code resource in combination with other resources like filemanager, model, or controller to extend the system\'s capabilities.'
        ],
        'skill' => [
            'desc' => 'The skill resource represents specific abilities and techniques.',
            'hint' => 'Additional abilities, normally attached to an intent but directly accessible to the chatbot.'
        ],
        'command' => [
            'desc' => 'The command resource represents system and application commands and their usage.',
            'hint' => 'Use commands to interact with resources or chain them together to automate tasks.'
        ],
        'info' => [
            'desc' => 'The info resource provides access to general encyclopedic knowledge and information.',
            'hint' => 'Leverage the info resource to augment the capabilities of other resources like search or howto.'
        ],
        'weather' => [
            'desc' => 'The weather resource provides information on weather conditions and forecasts.',
            'hint' => 'Combine the weather resource with the variable resource to store weather data for future use or analysis.'
        ],
        'website' => [
            'desc' => 'The website resource is your built in web browser for reading documents on the Internet.',
            'hint' => 'Use the website resource along side search to get more data and deeper insights.'
        ],
        'search' => [
            'desc' => 'The search resource provides tools for finding and retrieving information.',
            'hint' => 'Integrate the search resource with other resources like encyclopedia, howto, or news to enhance the system\'s ability to find relevant information.'
        ],
        'howto' => [
            'desc' => 'The howto resource offers guidance on performing specific tasks or solving problems.',
            'hint' => 'Use the howto resource in conjunction with other resources like code, skill, or command to help users learn and accomplish tasks.'
        ],
        'news' => [
            'desc' => 'The news resource provides access to current news and events.',
            'hint' => 'Combine the news resource with the search resource to help users find the latest information on specific topics.'
        ],
        'variable' => [
            'desc' => 'The variable resource helps store and manage data in memory for future use.',
            'hint' => 'You should always immediately store any new requests, facts, ideas, or information you\'re presented with in your variables and check them often.'
        ],
        'file' => [
            'desc' => 'The file resource represents your files and notes.',
            'hint' => 'Use the file resource to store big chunks of information like research from web searches.'
        ],
        'todo' => [
            'desc' => 'The todo resource manages todo lists and their items.',
            'hint' => 'You should spend a lot of time organizing your todo list so you always stay on task.'
        ],
        'task' => [
            'desc' => 'An alias for `todo`.',
            'hint' => 'Add a task directly to a given todo list as a shortcut.'
        ],
        'schedule' => [
            'desc' => 'The schedule resource manages events, appointments, and reminders.',
            'hint' => 'Use the schedule resource to create, update, and manage events and deadlines, or integrate it with other resources like user or variable for personalized event management.'
        ],
        'queue' => [
            'desc' => 'The queue resource manages a First-In-First-Out (FIFO) data structure for storing and retrieving items in a specific order.',
            'hint' => 'Use the queue resource alongside other resources like user, variable, or todo to manage tasks or data in a sequential order.'
        ],
        'stack' => [
            'desc' => 'The stack resource manages a Last-In-First-Out (LIFO) data structure for storing and retrieving items in a specific order.',
            'hint' => 'Use the stack resource alongside other resources like user, variable, or todo to manage tasks or data with a priority-based order.'
        ],
        'ai' => [
            'desc' => 'The AI resource manages interactions with AI models and services, such as the Hugging Face platform.',
            'hint' => 'Use the AI resource in combination with other resources to access AI capabilities, like natural language processing, text generation, or data analysis.'
        ],
        'transcript' => [
            'desc' => 'The transcript resource manages transcription memory, allowing you to search and remember previous conversations.',
            'hint' => 'Use the transcript resource to search for specific keywords in past discussions, and use it alongside other resources to recall and leverage information from previous conversations.'
        ],
        'step' => [
            'desc' => 'The step resource represents a specific action or task in a process or workflow.',
            'hint' => 'Use the step resource to break down complex tasks into smaller, more manageable steps and ensure each step is completed before moving on to the next. Always update your steps when presented with a goal.'
        ],
        'calc' => [
            'desc' => 'The calculator resource handles mathematical calculations and expressions.',
            'hint' => 'Use the calculator resource to perform mathematical calculations and expressions accurately.',
        ],
        'action' => [
            'desc' => 'The action resource represents custom actions that can be performed within the system or by external services.',
            'hint' => 'Use the action resource to define and execute custom actions, including external API calls or custom workflows.'
        ],
        'api' => [
            'desc' => 'The API resource represents external API interactions and data processing.',
            'hint' => 'Use the API resource to connect to, interact with, and retrieve data from external APIs, allowing for data exchange and integration with other services.'
        ],
        'feature' => [
            'desc' => 'The feature resource contains information regarding the general features of the platform.',
            'hint' => 'Use this whenever you need to be reminded what the system is built to accomplish.'
        ],
        'note' => [
            'desc' => 'The notes resource is a great place to hold data as you transfer from one command to another.',
            'hint' => 'Use the notes resource to store and manage text notes on a scratchpad while transitioning between commands or tasks. This resource helps you keep track of important information and makes it easy to access when needed.'
        ],
    ];

}
