<?php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\BlueCore\Command\CommandProcessor;

class CommandResource extends Service {
	protected $_collection;
	protected $_grammar;
	protected $_processor;

    protected $_page;
    protected $_perPage;

	public function __construct(CommandProcessor $processor)
	{
		$this->_processor = $processor;

		$this->_page = (int)store('_system.command.page');
        $this->_perPage = (int)store('_system.command.per_page');

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 25;

		parent::__construct();
	}

	public function list($behavior, $args)
	{
		$page = count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];
        }

        $this->_page = $page;
		$processor = $this->_processor;
		$commands = $processor->availableCommands();

		if ($commands !== null) {
            if ($this->_perPage < 1) {
                $this->_perPage = 25;
            }

            $total = count($commands);
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

			$response = "Available system commands:\n";
            foreach ($commands as $command) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $command . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} commands. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type any of the above commands to execute them." . PHP_EOL;
            $response .= "Type `previous commands` or `next commands` to move through pages." . PHP_EOL;
        } else {
            $response = "No commands have been set.";
        }

		$this->_response = $response;
	}

	public function next($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->list($behavior, [$this->_perPage, $this->_page]);
    }

    public function previous($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->list($behavior, [$this->_perPage, $this->_page]);
    }

    public function all()
    {
        $response = "";
        
        $processor = $this->_processor;
        $commands = $processor->availableCommands();
        foreach ($commands as $command) {
            $response .= "  - " . $command . PHP_EOL;
        }

        $this->_response = $response;
    }

    public function help() {
        $response = "Available commands for the Command Helper:\n" .
            "- list all commands: List all available commands.\n" .
            "  \t\tUsage: list <number> commands, list <number> commands by <page>.\n" .
            "- previous commands: Scroll backwards through command list.\n" .
            "- next commands: Scroll forward through command list.\n" .
            "- help with commands: Show this help message.";
     
        $this->_response = $response;
    }

	public function __destruct()
    {
        store('_system.command.page', $this->_page);
        store('_system.command.per_page', $this->_perPage);
    }
}