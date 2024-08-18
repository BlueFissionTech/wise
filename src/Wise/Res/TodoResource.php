<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\Data\Storage\Disk;

class TodoResource extends BaseResource
{
    private $_storage;

    protected $_name = 'todo';
    protected $_actions = ['list', 'make', 'previous', 'next', 'create', 'open', 'edit', 'add', 'select', 'delete', 'search', 'help'];

    public function __construct()
    {
        $this->_itemName = 'list';
        $this->_subItemName = 'task';
        parent::__construct();

        $storagePath = OPUS_ROOT . '/storage/system';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $this->_storage = new Disk([
            'location' => $storagePath,
            'name' => "{$this->_location}.json",
        ]);
        $this->_storage->activate();
        $this->refreshList();
    }

    public function tasks($behavior, $args)
    {
        $this->add($args);
    }

    protected function search($args)
    {
        if (count($args) > 0) {
            $keyword = $args[0];
            $todos = [];
            $tasks = [];
            $this->_storage->read();
            $lists = $this->_storage->list;
            foreach ($lists as $listName => $list) {
                if (stripos($listName, $keyword) !== false) {
                    $todos[] = $listName;
                }
                foreach ($list as $item) {
                    if (stripos($item['name'], $keyword) !== false) {
                        $tasks[] = [
                            'listName' => $listName,
                            'taskName' => $item['name'],
                            'priority' => $item['priority'] ?? null,
                            'deadline' => $item['deadline'] ?? null,
                        ];
                    }
                }
            }
            usort($tasks, function ($a, $b) {
                if ($a['priority'] === $b['priority']) {
                    if ($a['deadline'] === $b['deadline']) {
                        return 0;
                    }
                    if ($a['deadline'] === null) {
                        return 1;
                    }
                    if ($b['deadline'] === null) {
                        return -1;
                    }
                    return $a['deadline'] > $b['deadline'] ? 1 : -1;
                }
                if ($a['priority'] === null) {
                    return 1;
                }
                if ($b['priority'] === null) {
                    return -1;
                }
                return $a['priority'] > $b['priority'] ? -1 : 1;
            });

            $response = "";
            if ( count($todos) > 0 ) {
                $response .= "Found Todos:\n";
                foreach ($todos as $todo) {
                    $response .= "- {$todo}\n";
                }
            } else {
                $response .= "No Todos found matching keyword {$keyword}.\n";
            }
            if ( count($tasks) > 0 ) {
                if ($response !== "") {
                    $response .= "\n\n";
                }
                $response .= "\nFound Tasks:\n";
                foreach ($tasks as $task) {
                    $response .= "- {$task['taskName']} (from todo: {$task['listName']})\n";
                }
            } else {
                $response .= "No Tasks found matching keyword {$keyword}.\n";
            }
            return $response;
        } else {
            return "Please provide a keyword to search for (ex: find todo \"<keyword>\").\n";
        }
    }

    protected function makeSubItem($subItemName, $args)
    {
        $description = $args[1] ?? null;
        $deadline = $args[2] ?? null;
        $priority = $args[3] ?? null;
        return [
            'name' => $subItemName,
            'priority' => $priority,
            'description' => $description,
            'deadline' => $deadline,
        ];
    }

    protected function refreshList()
    {
        $this->_entries = $this->_storage->read() ?? [];
    }

    protected function retrieve($key)
    {
        $this->refreshList();
        if ($this->_key) {
            $entry = $this->_entries[$key] ?? null;
        } else {
            $entry = $this->_entries;
        }
        return $entry;
    }

    protected function store()
    {
        $this->_storage->contents(json_encode($this->_entries));
        $this->_storage->assign($this->_entries);
        $this->_storage->write();
    }
}