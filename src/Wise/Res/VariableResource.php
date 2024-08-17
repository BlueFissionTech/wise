<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\Data\Storage\Disk;

class VariableResource extends BaseResource
{
    private $_storage;

    protected $_name = 'variable';
    protected $_actions = ['get', 'set', 'list', 'show', 'next', 'previous', 'search', 'delete', 'help'];
    protected $_listType;

    public function __construct()
    {
        $this->_key = null;
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
        $this->_selected = $this->retrieve('_') ?? ['_'];
        $this->_listType = store('_system.variable.list_type') ?? 'list';
    }

    protected function set($args)
    {
        parent::set($args);
        $this->store();
    }

    protected function get($args)
    {
        $this->refreshList();
        parent::get($args);
    }

    protected function show($args)
    {
        $this->_listType = 'show';
        $page = count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];
        }

        $this->showVariables($page);
        store('_system.variable.list_type', $this->_listType);
    }

    protected function list($args)
    {
        $this->_listType = 'list';
        $page = count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];
        }

        $this->listVariables($page);
        store('_system.variable.list_type', $this->_listType);
    }

    protected function showVariables($page = null)
    {
        if ($page === null) {
            $page = $this->_page;
        }
        $this->_page = $page;

        $variables = $this->_storage->read();
        if ($variables !== null) {
            $total = count($variables);
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

            foreach ($variables as $key => $value) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    if (is_array($value)) {
                        $value = "\n\t\t-- ".implode("\n\t\t-- ", $value);
                    }
                    $response .= "  - {$key}: {$value}" . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} variables. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type `get variable <name>` to view values." . PHP_EOL;
            $response .= "Type `previous variables` or `next variables` to move through pages." . PHP_EOL;

        } else {
            $response = "No variables have been set.";
        }

        $this->_response = $response;
    }

    protected function listVariables($page = null)
    {
        if ($page === null) {
            $page = $this->_page;
        }
        $this->_page = $page;

        $variables = $this->_storage->read();
        if ($variables !== null) {
            $total = count($variables);
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

            foreach ($variables as $key => $value) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $key . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} variables. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type `get variable <name>` to view values." . PHP_EOL;
            $response .= "Type `previous variables` or `next variables` to move through pages." . PHP_EOL;
        } else {
            $response = "No variables have been set.";
        }

        $this->_response = $response;
    }

    protected function next($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : null;
        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        if ($this->_listType == 'list') {
            $this->listVariables(max(1, $this->_page + 1));
        } else {
            $this->showVariables(max(1, $this->_page + 1));
        }
    }

    protected function previous($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : null;
        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        if ($this->_listType == 'list') {
            $this->listVariables(max(1, $this->_page - 1));
        } else {
            $this->showVariables(max(1, $this->_page - 1));
        }
    }

    protected function refreshList()
    {
        $this->_entries = $this->_storage->read();
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