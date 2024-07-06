<?php
// ResourceCommand.php
namespace BlueFission\BlueCore\Business\Commands;

use BlueFission\Services\Service;
use BlueFission\Behavioral\Behaviors\Behavior;

class ResourceCommand extends Service
{
    protected $_page;
    protected $_perPage;

    protected $knownResources = [
        'system', 'model', 'controller', 'user', 'filemanager', 'database', 'code', 'skill', 'command', 'info', 'weather', 'website', 'web', 'howto', 'news', 'variable', 'file', 'todo', 'queue', 'stack', 'schedule', 'ai', 'transcript', 'task', 'step', 'calc', 'action', 'api', 'feature', 'note', 'entity'
    ];

    public function __construct()
    {
        $this->_page = (int)store('_system.resource.page');
        $this->_perPage = (int)store('_system.resource.per_page');

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 25;

        parent::__construct();
    }

    public function handle($behavior, $args)
    {
        $action = $behavior->name();

        if (count($args) > 1 && $args[0] === 'help') {
            $this->_response = $this->help();
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
                break;
            default:
                if ($command == 'help') {
                    $this->_response = $this->help();
                } else {
                    $this->_response = "Invalid action specified. Type 'help with resources' for available options.";
                }
        }
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

            $response = "List of available resources:\n";
            foreach ($resources as $resource) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $resource . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} resources. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type 'show resource \"<resource>\"' for more information about a specific resource." . PHP_EOL;
            $response .= "Type `previous resources` or `next resources` to move through pages." . PHP_EOL;
        } else {
            $response = "No resources have been set.";
        }

        $this->_response = $response;
    }

    private function showResource($resourceName)
    {
        if (in_array($resourceName, $this->knownResources) && isset($this->resourceDescriptions[$resourceName])) {
            $this->_response = "Resource: " . $resourceName . "\n";
            $this->_response .= "Description: " . $this->resourceDescriptions[$resourceName]['desc'] . "\n";
            $this->_response .= "Hint: " . $this->resourceDescriptions[$resourceName]['hint'];
        } else {
            $this->_response = "Resource '{$resourceName}' not found.";
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

    public function __destruct()
    {
        store('_system.resource.page', $this->_page);
        store('_system.resource.per_page', $this->_perPage);
    }

    // Descriptions and hints for each resource
    private $resourceDescriptions = [
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
