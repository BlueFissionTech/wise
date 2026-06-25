<?php
namespace BlueFission\Wise\Res;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Num;
use BlueFission\Services\Service;
use BlueFission\Str;

abstract class BaseResource extends Service {
    protected $_entries = [];
    protected $_selected = null;
    protected $_page;
    protected $_perPage;
    protected $_subPage;
    protected $_perSubPage;
    protected $_key = 'name';
    protected $_id = 'id';
    protected $_name = 'sample';
    protected $_location = null;
    protected $_plural = null;
    protected $_itemName = null;
    protected $_subItemName = null;
    protected $_listAccessCommand;
    protected $_helpDetails = [];
    protected $_actions = ['list','show','next','previous','do','make','generate','delete','help'];
    protected const OUTPUT_EVENT = 'wise.resource.output';
    protected const OUTPUT_REFRESH_EVENT = 'wise.resource.output.refresh';
    protected const WAITING_EVENT = 'wise.resource.waiting';
    protected array $_expectedOptions = [];
    protected array $_expectedOptionsMeta = [];
    protected array $_outputMeta = [];
    protected ?string $_lastOutputHash = null;
    protected ?string $_lastOptionsHash = null;
    protected ?string $_currentAction = null;
    protected int $_outputPreviewLines = 3;
    protected int $_outputPreviewChars = 240;
    protected int $_outputFullMax = 2000;

	public function __construct( )
	{
        $this->_location = $this->_location ?? "{$this->_name}_data";
        $this->_page = (int)store("_system.{$this->_name}.page");
        $this->_perPage = (int)store("_system.{$this->_name}.per_page");
        $this->_subPage = (int)store("_system.{$this->_name}.todo_page");
        $this->_perSubPage = (int)store("_system.{$this->_name}.per_todo_page");

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 25;
        $this->_page = $this->_subPage > 0 ? $this->_subPage : 1;
        $this->_perPage = $this->_perSubPage > 0 ? $this->_perSubPage : 25;

        $item = $this->_itemName ?? $this->_name;

        $this->_listAccessCommand = $this->_listAccessCommand ?? "Type `show the {$this->_name} \"<{$this->_key}>\"` for any of the above ". $this->pluralize($item) ." to view more.";

		parent::__construct();
	}

    public function handle($behavior, $args)
    {
        $this->resetOutputTracking();
        $action = $behavior->name();
        $this->_currentAction = $action;

        $actions = [];
        foreach ($this->_actions as $verb) {
            $actions[] = $this->resolveAction($verb);
        }

        if (!Arr::has($actions, $action, true)) {
            $response = "I'm sorry, Invalid action for this resource ({$action})." . PHP_EOL . PHP_EOL;
            $response .= $this->help();
            $this->setOutputType('error', [
                'error' => 'invalid_action',
                'action' => $action,
            ]);
            $this->_response = $response;
            $this->emitOutputEventIfNeeded();
            $this->emitWaitingEventIfNeeded();
            return;
        }

        switch ($action) {
            case 'do':
                $this->execute($args);
                break;
            case 'make':
                $this->create($args);
                break;
            case 'edit':
                $this->edit($args);
                break;
            case 'save':
                $this->save($args);
                break;
            case 'generate':
                $this->generate($args);
                break;
            case 'set':
                $this->set($args);
                break;
            case 'get':
                $this->get($args);
                break;
            case 'find':
                $this->find($args);
                break;
            case 'all':
                $this->all();
                break;
            case 'list':
                $this->list($args);
                break;
            case 'next':
                $this->next($args);
                break;
            case 'previous':
                $this->previous($args);
                break;
            case 'show':
                $this->show($args);
                break;
            case 'select':
                $this->select($args);
                break;
            case 'add':
                $this->add($args);
                break;
            case 'delete':
                $this->delete($args);
                break;
            default:
            case 'help':
                $this->_response = $this->help();
                break;
        }

        $this->emitOutputEventIfNeeded();
        $this->emitWaitingEventIfNeeded();
    }

    protected function create($args)
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "";
        if (Arr::count($args) > 0) {
            $name = $args[0];
            $entry = $this->retrieve($name);
            if ($entry === null) {
                $entry = $this->makeItem($name, $args);
                if ($entry == null) {
                    return;
                }
                $this->keep($entry);
                $response = ucfirst($item)." '{$name}' created'\n";
                $this->setOutputType('create', [
                    'status' => 'created',
                    'name' => $name,
                ]);
            } else {
                $response = ucfirst($item)." '{$name}' already exists.\n";
                $this->setOutputType('create', [
                    'status' => 'exists',
                    'name' => $name,
                ]);
            }
        } else {
            $response = "Please provide a name for the new $item (ex: `create {$this->_name} \"<{$this->_key}>\"`).\n";
            $this->setOutputType('error', [
                'error' => 'missing_name',
                'action' => 'create',
            ]);
        }

        $this->_response = $response;
    }

    protected function generate($args)
    {
        $title = $args[0] ?? '';
        $prompt = $args[1] ?? '';

        $item = $this->_itemName ?? $this->_name;

        // Check if the OpenAI API key is set
        if (!env('OPEN_AI_API_KEY')) {
            $this->_response = "OpenAI API key is not set.";
            $this->setOutputType('error', [
                'error' => 'missing_api_key',
                'action' => 'generate',
            ]);
            return;
        }

        $sampleJson = $this->jsonSample();

        // Initialize the OpenAIService
        $openAIService = new OpenAIService();

        // Generate the object using GPT-3
        $gpt3_prompt = "Sample:\n$sampleJson\n\nTitle:\"$title\" \n\nInstructions:\"$prompt\" \n\nUsing the example, generate a JSON file representing the described {$tiem}: ";
        $gpt3_response = $openAIService->complete($gpt3_prompt, ['max_tokens'=>3000]);

        // Check for errors in the response
        if (isset($gpt3_response['error'])) {
            $this->_response = "Error generating {$item}.";
            $this->setOutputType('error', [
                'error' => 'generation_failed',
                'action' => 'generate',
                'item' => $item,
            ]);
            return;
        }

        // Get the generated object
        $object = Str::trim($gpt3_response['choices'][0]['text']);

        $this->_response = $object;
        $this->setOutputType('generate', [
            'title' => $title,
        ]);
    }

    protected function set($args)
    {
        $item = $this->_itemName ?? $this->_name;

        if (isset($args) && isset($args[0]) && isset($args[1])) {
            $key = $args[0] ?? null;
            $value = $args[1] ?? null;
            if ($this->_selected) {
                $entry = $this->_selected;
            } elseif (isset($args[2])) {
                $name = $args[2];
                $entry = $this->retrieve($name);
            } else {
                $this->_response = "Please provide a property to get the value of the {$item}.";
            }
            
            if (isset($entry)) {
                $entry[$key] = $value;
                $this->keep($entry);
                $this->_response = ucfirst($item)." '{$key}' has been set to '{$value}'.";
                $this->setOutputType('update', [
                    'property' => $key,
                    'value' => $value,
                    'name' => $entry[$this->_key] ?? $key,
                ]);
            } else {
                $this->_response = ucfirst($item)." '{$key}' not found.";
                $this->setOutputType('error', [
                    'error' => 'not_found',
                    'action' => 'set',
                    'property' => $key,
                ]);
            }
        } else {
            $this->_response = "Please provide a property and a value to set the {$item}.";
            $this->setOutputType('error', [
                'error' => 'missing_property',
                'action' => 'set',
            ]);
        }
    }

    protected function get($args)
    {
        $item = $this->_itemName ?? $this->_name;
        if (isset($args) && isset($args[0])) {
            $key = $args[0] ?? '';
            if ($this->_selected) {
                $entry = $this->_selected;
            } elseif (isset($args[1])) {
                $name = $args[1];
                $entry = $this->retrieve($name);
            }

            $property = Str::trim((string)$key);
            if (isset($entry) && isset($entry[$property]) && $entry[$property] !== '') {
                $value = $entry[$property];
                $this->_response = "The value of '{$key}' is '{$value}'.";
                $this->setOutputType('property', [
                    'property' => $key,
                    'value' => $value,
                    'name' => $entry[$this->_key] ?? null,
                ]);
            } else {
                $this->_response = ucfirst($this->_name)." '{$key}' not found.";
                $this->setOutputType('error', [
                    'error' => 'not_found',
                    'action' => 'get',
                    'property' => $key,
                ]);
            }
            return;
        } else {
            $this->_response = "Please provide a property to get the value of the {$item}.";
            $this->setOutputType('error', [
                'error' => 'missing_property',
                'action' => 'get',
            ]);
        }
    }

	protected function list($args)
	{
        $item = $this->_itemName ?? $this->_name;

        $this->refreshList();
        $response = "";
        if (Arr::count($args) > 0 && !Num::is($args[0])) {
            $key = $args[0];
            $list = $this->retrieve($key);

            if (isset($list)) {
                $response = $this->subList($args);
            } else {
                $response = "'".ucfirst($item)." '{$key}' not found. Use `list all ".$this->pluralize($this->_name)."` to see available lists.\n";
                $this->setOutputType('error', [
                    'error' => 'not_found',
                    'action' => 'list',
                    'name' => $key,
                ]);
            }
        } else {
            $response = $this->mainList($args);
        }
        $this->setExpectedOptions($this->defaultListOptions($args), ['source' => 'list']);
        $this->_response = $response;
	}

    protected function mainList($args)
    {
        $item = $this->_itemName ?? $this->_name;
        $plural = $this->pluralize($this->_name);
        $page = Arr::count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (Arr::count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];
        }

        $this->_page = $page;
        $entries = Arr::keys($this->_entries);

        if ($entries !== null) {
            if ($this->_perPage < 1) {
                $this->_perPage = 25;
            }

            $total = Arr::count($entries);
            $totalPages = ceil($total / $this->_perPage);

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;

            $pageStart = ($page - 1) * $this->_perPage;
            $pageEnd = $pageStart + $this->_perPage;

            $i = 0;
            $count = 0;
            $response = "";
            $pageItems = [];

            $response = "Available ". $this->pluralize($item) .":" . PHP_EOL;
            foreach ($entries as $entry) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $entry . PHP_EOL;
                    $count++;
                    $pageItems[] = $entry;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} ". $this->pluralize($item) .". Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= $this->_listAccessCommand . PHP_EOL;
            $response .= "Type `previous ". $this->pluralize($this->_name) ."` or `next ". $this->pluralize($this->_name) ."` to move through pages." . PHP_EOL;
            $response .= "Type `help with ". $this->pluralize($this->_name) ."` for more options." . PHP_EOL;

            $this->setOutputType('list', [
                'list_type' => 'main',
                'page' => $page,
                'per_page' => $this->_perPage,
                'total' => $total,
                'count' => $count,
                'items' => $pageItems,
            ]);
        } else {
            $response = "No ". $this->pluralize($item) ." found.";
            $this->setOutputType('list', [
                'list_type' => 'main',
                'page' => $page,
                'per_page' => $this->_perPage,
                'total' => 0,
                'count' => 0,
                'items' => [],
            ]);
        }

        return $response;
    }

    protected function subList($args)
    {
        if (Arr::count($args) > 2) {
            $this->_perSubPage = (int)$args[1];
            $this->_subPage = (int)$args[2];
        } elseif (Arr::count($args) > 1) {
            $this->_subPage = (int)$args[1];
        }

        $listName = ($args[0] ?? null);
        $list = isset($args[0]) ? $this->retrieve($args[0]) : $this->_selected;
        
        $page = $this->_subPage;
        $perPage = $this->_perSubPage ;


        if (isset($list) && Arr::is($list)) {
            if ($perPage < 1) {
                $perPage = 25;
            }
            $total = Arr::count($list);
            $totalPages = ceil($total / $perPage);

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;

            $start = ($page - 1) * $perPage;
            $end = $start + $perPage - 1;
            $response = ucfirst($this->pluralize($this->_itemName))." in {$listName}:\n";
            $i = 0;
            $count = 0;
            $pageItems = [];
            foreach ($list as $title=>$entry) {
                if ($i >= $start && $i <= $end) {
                    $name = (Str::is($entry) ? $entry : $entry['name']);
                    $response .= "- {$name}\n";
                    $count++;
                    $pageItems[] = $name;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} ".$this->pluralize($this->_itemName)." for {$listName}. Page {$page} of {$totalPages}. Type `previous {$this->_name} {$listName}` or `next {$this->_name} {$listName}` to move through pages.\n";
            $this->setOutputType('list', [
                'list_type' => 'sub',
                'list_name' => $listName,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'count' => $count,
                'items' => $pageItems,
            ]);
        } else {
            $response = "{$this->_itemName} '{$listName}' not found. Use `list all ".$this->pluralize($this->_name)."` to see available ".$this->pluralize($this->_itemName).".\n";
            $this->setOutputType('error', [
                'error' => 'not_found',
                'action' => 'list',
                'name' => $listName,
            ]);
        }

        return $response;
    }

	protected function next($args)
    {
        $perPage = Arr::count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->list([$this->_perPage, $this->_page]);
    }

    protected function previous($args)
    {
        $perPage = Arr::count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->list([$this->_perPage, $this->_page]);
    }

    protected function all()
    {
        $response = "";
        $entries = $this->getAll();
        foreach ($entries as $object) {
            $response .= $this->formatListItem($object) . PHP_EOL;
        }

        $this->_response = $response;
        $this->setOutputType('list', [
            'list_type' => 'all',
            'count' => Arr::count($entries),
            'items' => $entries,
        ]);
    }


    protected function find($args)
    {
        $item = $this->_itemName ?? $this->_name;
        $plural = $this->pluralize($this->_name);

        if (Arr::count($args) > 0) {
            $keyword = $args[0];
            $lists = [];
            $objects = [];

            $entries = $this->_entries;

            foreach ($entries as $name => $entry) {
                if (Str::has(Str::lower((string)$name), Str::lower((string)$keyword))) {
                    $lists[] = $name;
                }

                if ( Arr::is($entry) ) {
                    foreach ($entry as $object) {
                        if ( Arr::is($object) ) {
                            if (Str::has(Str::lower((string)$object[$this->_key]), Str::lower((string)$keyword))) {
                                $objects[] = [
                                    'parent' => $name,
                                    $this->_key => $item[$this->_key]
                                ];
                            }
                        } else {
                            if (Str::has(Str::lower((string)$object), Str::lower((string)$keyword))) {
                                $objects[] = [
                                    'parent' => $name,
                                    $this->_key => $object
                                ];
                            }
                        }
                    }
                }
            }
            if ( Arr::isNotEmpty($objects) ) {
                usort($objects, function ($a, $b) {
                    if ($a[$this->_key] === null) {
                        return 1;
                    }
                    if ($b[$this->_key] === null) {
                        return -1;
                    }
                    return $a[$this->_key] > $b[$this->_key] ? -1 : 1;
                });
            }

            $response = "";
            if ( Arr::count($lists) > 0 ) {
                $response .= "Found {$plural}:\n";
                foreach ($lists as $list) {
                    $response .= "- {$list}\n";
                }
            } else {
                $response .= "No {$plural} found matching keyword(s) '{$keyword}'.\n";
            }

            if ( $this->_subItemName ) {
                if ($response !== "") {
                    $response .= "\n";
                }
                if ( Arr::count($objects) > 0 ) {
                    $response .= "Found ".$this->pluralize($this->_subItemName).":\n";
                    foreach ($objects as $object) {
                        $response .= "- {$object[$this->_key]} (from {$item}: {$object['parent']})\n";
                    }
                } else {
                    $response .= "No ".$this->pluralize($this->_subItemName)." found matching keyword(s) '{$keyword}'.\n";
                }
            }
            $itemMatches = [];
            foreach ($objects as $object) {
                if (isset($object[$this->_key])) {
                    $itemMatches[] = $object[$this->_key];
                }
            }
            $this->setOutputType('search', [
                'keyword' => $keyword,
                'list_matches' => $lists,
                'item_matches' => $itemMatches,
                'list_count' => Arr::count($lists),
                'item_count' => Arr::count($itemMatches),
            ]);
            $this->_response = $response;
        } else {
            $verb = 'find';
            foreach ($this->_verbs['find'] as $verb) {
                if ( Arr::has($this->_actions, $verb, true) ) {
                    break;
                }
            }

            $prep = $verb == 'search' ? 'for' : 'with';
            $this->_response = "Please provide a keyword to {$verb} by (ex: {$verb} {$plural} {$prep} \"<keyword>\").\n";
            $this->setOutputType('error', [
                'error' => 'missing_keyword',
                'action' => 'find',
            ]);
        }
    }

    protected function show($args)
    {
        $name = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$name) {
            $this->_response = "You must provide the name of the {$this->_name} to display.";
            $this->setOutputType('error', [
                'error' => 'missing_name',
                'action' => 'show',
            ]);
            return;
        }

        $entry = $this->retrieve($name);

        if (!$entry) {
            $this->_response = ucfirst($item)." '$name' not found.";
            $this->setOutputType('error', [
                'error' => 'not_found',
                'action' => 'show',
                'name' => $name,
            ]);
            return;
        }

        $this->_response = $this->formatDetails($entry);
        $this->setOutputType('detail', [
            'name' => $name,
            'entry' => $entry,
        ]);
    }

    protected function select($args)
    {
        $id = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$id) {
            $this->_response = "You must provide the id of the {$item} to display.";
            $this->setOutputType('error', [
                'error' => 'missing_id',
                'action' => 'select',
            ]);
            return;
        }

        $this->_selected = $this->retrieve($id);

        if (!$this->_selected) {
            $this->_response = ucfirst($item)." '$id' not found.";
            $this->setOutputType('error', [
                'error' => 'not_found',
                'action' => 'select',
                'id' => $id,
            ]);
            return;
        }

        $this->_response = $this->formatDetails($this->_selected);
        $this->setOutputType('detail', [
            'id' => $id,
            'entry' => $this->_selected,
        ]);
    }

    protected function edit($args)
    {
        $name = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$name) {
            $this->_response = "You must provide the name of the {$item} to edit.";
            return;
        }

        $entry = $this->retrieve([$name]);
        foreach ($this->_editSignature as $index => $property) {
            if (isset($args[$index])) {
                if (isset($args[$index])) {
                    $entry[$property] = $args[$index];
                }
            }
        }
        $this->keep($entry);

        $this->_response = ucfirst($item)." '{$name}' has been updated.";
        $this->setOutputType('update', [
            'name' => $name,
        ]);
    }

    protected function save($args)
    {
        $this->store();
        $item = $this->_itemName ?? $this->_name;

        $this->_response = ucfirst($item)." has been saved.";
        $this->setOutputType('save', [
            'name' => $item,
        ]);
    }

    protected function add($args)
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "";
        if (Arr::count($args) == 1) {
            $parts = explode(' ', $args[0], 2);
        } elseif (Arr::count($args) > 1) {
            $parts = $args;
        } else {
            $parts = [];
        }

        if (Arr::count($parts) > 1) {
            $itemName = isset($parts[0]) ? $parts[0] : null;
            $this->_listName = $listName = $parts[1];
            $list = $this->retrieve($listName);
            if ($list !== null) {
                $itemExists = false;
                foreach ($list as $entry) {
                    $name = (Str::is($entry) ? $entry : $entry['name']);
                    if ($name === $itemName) {
                        $itemExists = true;
                        break;
                    }
                }

                if (!$itemExists) {
                    $entry = $this->makeSubItem($itemName, $args);
                    $list[$itemName] = $entry;
                    $this->keep($list);
                    $response = "Item '{$itemName}' added to {$item} '{$listName}'.\n";
                    $this->setOutputType('add', [
                        'status' => 'added',
                        'name' => $itemName,
                        'list' => $listName,
                    ]);
                } else {
                    $response = "Item '{$itemName}' already exists in list '{$listName}'.\n";
                    $this->setOutputType('add', [
                        'status' => 'exists',
                        'name' => $itemName,
                        'list' => $listName,
                    ]);
                }
            } else {
                $response = "List '{$listName}' not found. Use `list todo` to see available lists.\n";
                $this->setOutputType('error', [
                    'error' => 'not_found',
                    'action' => 'add',
                    'list' => $listName,
                ]);
            }
        } else {
            $response = "Please provide the list name and item name (ex: add \"<item description>\" to the {$this->_name} \"<list name>\").\n";
            $this->setOutputType('error', [
                'error' => 'missing_name',
                'action' => 'add',
            ]);
        }

        $this->_response = $response;
    }

    protected function help()
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "Available {$item} commands:" . PHP_EOL;

        foreach ($this->_actions as $action) {
            $lines = $this->getHelp($action);

            foreach ($lines as $line) {
                $response .= $line . PHP_EOL;
            }
        }

        $this->setOutputType('help', [
            'commands' => $this->_actions,
        ]);

        return $response;
    }

    protected function getHelp($action) 
    {
        $verb = $this->resolveAction($action);
        $a = $this->article($this->_name);
        $item = $this->_itemName ?? $this->_name;
        $a2 = $this->article($item);
        $singular = $this->_name;
        $plural = $this->pluralize($this->_name);
        $key = $this->_key ?? 'name';
        $id = $this->_id ?? 'id';

        $helpDetails = [
            'go' => [
                "  - {$action}: Go to the {$item} with the given id.",
                "      Usage `{$action} to the {$singular} \"<id>\" [\"<properties...>\"]`",
            ],
            'find' => [
                "  - {$action}: find the {$plural} that match a given search term.",
                "      Usage `{$action} {$plural} ".($action == 'find' ? 'with' : 'for')." \"<keyword>\"`",
            ],
            'do' => [
                "  - {$action}: Execute the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\" [ with \"<properties...>\"]`",
            ],
            'set' => [
                "  - {$action}: Set the {$item} property named 'name' to 'value'.",
                "      Usage `{$action} the {$singular} \"<name>\" to \"<value>\"`",
            ],
            'get' => [
                "  - {$action}: Get the {$item} property named 'name'.",
                "      Usage `{$action} the {$singular} \"<name>\"`",
            ],
            'generate' => [
                "  - {$action}: Create a new {$item} with the given description.",
                "      Usage `{$action} {$a} {$singular} from \"<description>\"`",
            ],
            'make' => [
                "  - {$action}: Create a new {$item} with the given properties.",
                "      Usage `{$action} {$a} {$singular} \"<{$this->_key}>\" [\"<properties...>\"]`",
            ],
            'open' => [
                "  - {$action}: Open the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\"`",
            ],
            'add' => [
                "  - {$action}: Add {$a2} {$item} to the selected {$this->_name}.",
                "      Usage `{$action} \"<{$item}>\" to the \"<{$this->_key}>\" {$singular}`",
            ],
            'edit' => [
                "  - {$action}: Edit the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\"`",
            ],
            'list' => [
                "  - {$action}: List all available {$plural}, with an optional pagination.",
                "      Usage `{$action} <perPage> {$plural} [on <page>]`",
                "      Usage `{$action} {$plural} by <perPage> [on <page>]`",
            ],
            'next' => [
                "  - {$action}: Show the next page of the {$plural} list.",
                "      Usage `{$action} <perPage> {$plural}`",
            ],
            'previous' => [
                "  - {$action}: Show the previous page of the {$plural} list.",
                "      Usage `{$action} <perPage> {$plural}`",
            ],
            'show' => [
                "  - {$action}: Show a detailed breakdown of the {$item} and its details.",
                "      Usage `{$action} the {$singular} \"<{$key}>\"`",
            ],
            'select' => [
                "  - {$action}: Select the {$item} with the given {$id}.",
                "      Usage `{$action} {$singular} by \"<{$id}>\"`",
            ],
            'delete' => [
                "  - {$action}: Delete the {$item} with the given {$key}.",
                "      Usage `{$action} the {$singular} \"<{$key}>\"`",
            ],
            'help' => [
                "  - {$action}: Show this help message.",
                "      Usage `{$action} with {$plural}`",
            ],

        ];

        if ( $this->_subItemName ) {
            $helpDetails['list'][] = "      Usage `{$action} \"<name>\" {$singular}`";
        }

        foreach ( $this->_helpDetails as $behavior => $details ) {
            $helpDetails[$behavior] = $this->_helpDetails[$behavior];
        }

        return $helpDetails[$verb] ?? [];
    }

    protected function getAll()
    {
        $this->refreshList();
        $entries = $this->_entries;
        $entries = Arr::keys($entries);

        return $entries;
    }

    protected function refreshList()
    {
        $this->_entries = store("_system.{$this->_name}.list") ?? [];
    }

    protected function retrieve($key)
    {
        $entry = $this->_entries[$key] ?? null;
        return $entry;
    }

    protected function keep($entry)
    {
        $this->_selected = $entry;
        $key = $this->_selected[$this->_key] ?? (Arr::is($this->_entries) ? Arr::count($this->_entries) -1 : 0);
        // die(var_dump($this->_listName, $this->_entries[$this->_listName], $key, $this->_key, $entry));

        if ( $this->_subItemName && $this->_listName && $this->_key ) {
            // $listName = store("_system.{$this->_name}.list_name") ?? '';
            $this->_entries[$this->_listName] = $this->_selected;
            $this->_listName = null;
        } elseif ( $this->_key ) {
            $this->_entries[$key] = $this->_selected;
        } else {
            $this->_entries = $this->_selected;
        }

        $this->store();
    }

    protected function store()
    {
        store("_system.{$this->_name}.list", $this->_entries);
    }

    protected function formatListItem($entry)
    {
        return "  - " . $entry;
    }

    protected function resolveAction($action)
    {
        foreach ($this->_verbs as $verb => $aliases) {
            if (Arr::has($aliases, $action, true)) {
                return $verb;
            }
        }

        return $action;
    }

    protected function formatDetails($entry)
    {
        $response = "Name: {$entry['name']}" . PHP_EOL;
        $response .= "Description: {$entry['description']}" . PHP_EOL;
        return $response;
    }

    protected function makeItem($itemName, $args)
    {
        $key = $this->_key ?? null;
        return $key ? [$key=>$itemName] : $itemName;
    }

    protected function makeSubItem($subItemName, $args)
    {
        $key = $this->_key ?? null;
        return $key ? [$key=>$subItemName] : $subItemName;
    }

    public function __destruct()
    {
        store("_system.{$this->_name}.page", $this->_page);
        store("_system.{$this->_name}.per_page", $this->_perPage);
        store("_system.{$this->_name}.sub_page", $this->_subPage);
        store("_system.{$this->_name}.per_sub_page", $this->_perSubPage);
        // store("_system.{$this->_name}.selected", $this->_selected[$this->_key] ?? null);
    }

    public function pluralize($text)
    {
        return (Str::is($this->_plural) && $this->_plural !== '' && $text == $this->_name) ? $this->_plural : Str::pluralize($text);
    }

    public function article($text)
    {
        $a = (Arr::has(['a', 'e', 'i', 'o', 'u'], Str::sub($text, 0, 1), true) ? 'an' : 'a');
        return $a;
    }

    protected function setOutputType(string $type, array $meta = []): void
    {
        $payload = Arr::merge([
            'output_type' => $type,
            'item' => $this->_itemName ?? $this->_name,
        ], $meta);

        $this->mergeOutputMeta($payload);
    }

    /**
     * Provide additional metadata (styles, highlights, transcripts) for output events.
     *
     * Override or call this in resource subclasses when emitting colored/structured output
     * so that listeners (LLMs/agents) can reconstruct the view or extract intent-ready data.
     */
    protected function setOutputMeta(array $meta): void
    {
        $this->_outputMeta = $meta;
    }

    protected function mergeOutputMeta(array $meta): void
    {
        $this->_outputMeta = Arr::merge($this->_outputMeta, $meta);
    }

    /**
     * Set expected options to emit when the console is awaiting input.
     *
     * These are surfaced as a waiting-state event exactly once per output,
     * so dynamic display refreshes don't spam listeners.
     */
    protected function setExpectedOptions(array|string $options, array $meta = []): void
    {
        $options = Arr::is($options) ? $options : [$options];
        $normalized = [];
        foreach ($options as $option) {
            $option = Str::trim((string)$option);
            if ($option === '') {
                continue;
            }
            $normalized[$option] = true;
        }

        $this->_expectedOptions = Arr::keys($normalized);
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

    /**
     * Emit a single output-change event with a truncated preview, plus optional markdown.
     *
     * Listeners can use this to capture the first screen-visible lines without
     * subscribing to every dynamic screen redraw.
     */
    protected function emitOutputEventIfNeeded(?string $output = null): void
    {
        $output = $output ?? $this->_response ?? '';
        if (!Str::is($output) || $output === '') {
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
        $options = $this->expectedOptionsForOutput($output);

        $payload = Arr::merge([
            'output' => $preview['text'],
            'lines' => $preview['lines'],
            'truncated' => $preview['truncated'],
            'length' => Str::len($output),
            'markdown' => $markdown,
            'hash' => $hash,
        ], $this->buildFullOutputMeta($output), $this->_outputMeta, $this->buildEventEnvelope($hash, $options));

        $payload = Dev::apply('wise.resource.output.meta', $payload);

        $this->dispatch(self::OUTPUT_EVENT, new Meta(data: $payload, src: $this));
        $this->dispatch(Event::CHANGE, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.output.changed', $payload);
    }

    /**
     * Emit a lightweight refresh signal when the output hasn't changed.
     *
     * This is lower priority than the unique output event but lets observers
     * detect re-renders or repeated command output.
     */
    protected function emitOutputRefreshEvent(string $output): void
    {
        $preview = $this->buildOutputPreview($output);
        $hash = sha1($output);
        $options = $this->expectedOptionsForOutput($output);
        $payload = Arr::merge([
            'output' => $preview['text'],
            'lines' => $preview['lines'],
            'truncated' => $preview['truncated'],
            'length' => Str::len($output),
            'hash' => $hash,
        ], $this->buildFullOutputMeta($output), $this->_outputMeta, $this->buildEventEnvelope($hash, $options), [
            'repeated' => true,
        ]);

        $payload = Dev::apply('wise.resource.output.refresh.meta', $payload);

        $this->dispatch(self::OUTPUT_REFRESH_EVENT, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.output.refresh', $payload);
    }

    /**
     * Emit a waiting-state event once per output to advertise default options.
     *
     * Options can be explicitly set via setExpectedOptions() or auto-extracted
     * from bracketed lists in the response.
     */
    protected function emitWaitingEventIfNeeded(?string $output = null): void
    {
        $output = $output ?? $this->_response ?? '';
        if (!Str::is($output) || $output === '') {
            return;
        }

        $options = $this->expectedOptionsForOutput($output);

        if ($options === []) {
            return;
        }

        $hash = sha1(implode('|', $options) . '|' . $output);
        if ($hash === $this->_lastOptionsHash) {
            return;
        }
        $this->_lastOptionsHash = $hash;

        $payload = Arr::merge([
            'state' => 'waiting',
            'options' => $options,
        ], $this->_expectedOptionsMeta, $this->buildWaitingEnvelope(sha1($output), $options));

        $payload = Dev::apply('wise.resource.waiting.meta', $payload);

        $this->dispatch(self::WAITING_EVENT, new Meta(data: $payload, src: $this));
        $this->dispatch(Event::STATE_CHANGED, new Meta(data: $payload, src: $this));
        Dev::do('wise.resource.waiting', $payload);
    }

    protected function buildOutputPreview(string $output): array
    {
        $normalized = Str::replace($output, "\r\n", "\n");
        $normalized = Str::replace($normalized, "\r", "\n");
        $lines = preg_split('/\n/', $normalized);
        if (!Arr::is($lines) || $lines === []) {
            $lines = [$output];
        }

        $previewLines = Arr::slice($lines, 0, $this->_outputPreviewLines);
        $previewText = implode(PHP_EOL, $previewLines);
        $truncated = Arr::count($lines) > $this->_outputPreviewLines;

        if (Str::len($previewText) > $this->_outputPreviewChars) {
            $previewText = Str::sub($previewText, 0, $this->_outputPreviewChars);
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
        if (Str::len($output) <= $this->_outputFullMax) {
            return ['full_output' => $output];
        }

        return [];
    }

    protected function expectedOptionsForOutput(string $output): array
    {
        if ($this->_expectedOptions !== []) {
            return $this->_expectedOptions;
        }

        return $this->extractOptionsFromResponse($output);
    }

    protected function buildEventEnvelope(string $outputHash, array $options = []): array
    {
        $status = $this->eventStatus($options);
        $envelope = [
            'output_id' => $this->outputId($outputHash),
            'resource' => $this->_name,
            'resource_name' => $this->_name,
            'action' => $this->_currentAction ?? 'unknown',
            'status' => $status,
            'waiting' => $status === 'waiting',
            'completed' => $status !== 'waiting',
            'timestamp' => date('c'),
            'repeated' => false,
        ];

        if (array_key_exists('status', $this->_outputMeta)) {
            $envelope['resource_status'] = $this->_outputMeta['status'];
        }

        if (!array_key_exists('semantic_metadata', $this->_outputMeta)) {
            $envelope['semantic_metadata'] = null;
        }

        return $envelope;
    }

    protected function buildWaitingEnvelope(string $outputHash, array $options): array
    {
        return [
            'output_id' => $this->outputId($outputHash),
            'resource' => $this->_name,
            'resource_name' => $this->_name,
            'action' => $this->_currentAction ?? 'unknown',
            'status' => 'waiting',
            'waiting' => true,
            'completed' => false,
            'timestamp' => date('c'),
            'options_hash' => sha1(implode('|', $options)),
        ];
    }

    protected function outputId(string $outputHash): string
    {
        $source = implode('|', [
            $this->_name,
            $this->_currentAction ?? 'unknown',
            $outputHash,
        ]);

        return 'wise-out-' . Str::sub(sha1($source), 0, 16);
    }

    protected function eventStatus(array $options): string
    {
        if (($this->_outputMeta['output_type'] ?? null) === 'error'
            || array_key_exists('error', $this->_outputMeta)) {
            return 'error';
        }

        return $options === [] ? 'completed' : 'waiting';
    }

    protected function extractOptionsFromResponse(string $response): array
    {
        if (!preg_match('/\[(.*?)\]/', $response, $matches)) {
            return [];
        }

        $raw = $matches[1] ?? '';
        $parts = preg_split('/,|\bor\b/i', $raw);
        if (!Arr::is($parts)) {
            return [];
        }

        $options = [];
        foreach ($parts as $part) {
            $part = Str::trim($part, " \t\n\r\0\x0B\"");
            if ($part === '') {
                continue;
            }
            $options[$part] = true;
        }

        return Arr::keys($options);
    }

    protected function defaultListOptions(array $args): array
    {
        $plural = $this->pluralize($this->_name);
        $options = [
            "previous {$plural}",
            "next {$plural}",
            "help with {$plural}",
        ];

        if (Arr::count($args) > 0 && !Num::is($args[0])) {
            $listName = (string)$args[0];
            $options[] = "previous {$this->_name} {$listName}";
            $options[] = "next {$this->_name} {$listName}";
        }

        return $options;
    }

    private $_verbs = [
        'do' => ['do','perform','enact', 'run', 'use'],
        'go' => ['go', 'navigate'],
        'make' => ['make', 'create'],
        'get' => ['get', 'retrieve'],
        'set' => ['set', 'change', 'assign'],
        'update' => ['update'],
        'delete' => ['delete', 'remove'],
        'generate' => ['generate', 'build'],
        'message' => ['message'],
        'open' => ['open'],
        'input' => ['input', 'enter'],
        'send' => ['send', 'submit'],
        'edit' => ['edit'],
        'add' => ['add','append'],
        'download' => ['download'],
        'show' => ['show', 'display', 'view'],
        'list' => ['list'],
        'find' => ['find', 'search', 'locate'],
        'previous' => ['previous', 'back'],
        'next' => ['next', 'forward'],
        'select' => ['select', 'choose', 'take', 'check'],
        'help' => ['help', 'assist'],
        'save' => ['save', 'preserve', 'store'],
        'cancel' => ['cancel', 'stop', 'nevermind'],
        'prompt' => ['prompt', 'ask', 'inquire'],
        'less' => ['less', 'fewer'],
        'more' => ['more', 'greater'],
        //Special system root commands
        'scroll' => ['scroll'],
        'copy' => ['copy', 'duplicate'],
        'cut' => ['cut', 'remove'],
        'paste' => ['paste', 'insert'],
        'move' => ['move', 'shift'],
    ];
}
