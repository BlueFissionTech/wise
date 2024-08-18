<?php
// SearchCommand.php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\SimpleClients\GoogleSearchClient;
use BlueFission\SimpleClients\DuckDuckGoSearchClient;

class SearchResource extends Service
{
    private $googleSearchClient;
    private $duckDuckGoSearchClient;
    private $_page;
    private $_perPage;
    private $_results;

    public function __construct(GoogleSearchClient $googleSearchClient, DuckDuckGoSearchClient $duckDuckGoSearchClient)
    {
        $this->googleSearchClient = $googleSearchClient;
        $this->duckDuckGoSearchClient = $duckDuckGoSearchClient;

        $this->_page = (int)store('_system.search.page');
        $this->_perPage = (int)store('_system.search.per_page');
        $this->_results = store('_system.search.results') ?? [];

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 5;

        parent::__construct();
    }

    public function handle($behavior, $args)
    {
        $action = '';
        $action = $behavior->name();

        $response = "Invalid action '{$action} specified.\n\n";
        $response .= $this->help();


        if ($action == 'go' ) {
            $this->navigate($args);
            return;
        } elseif ($action == 'find' || $action == 'search') {
            $this->search($args);
            return;
        } elseif ($action == 'previous') {
            $this->previous($args);
            return;
        } elseif ($action == 'next') {
            $this->next($args);
            return;
        } elseif ($action == 'help') {
            $response = $this->help();
            return;
        } else {
            $response = "I'm not sure how to handle that request. Type 'get search help' for available options.\n\n";
            $response .= $this->help();
        }

        $this->_response = $response;

    }

    private function search($args)
    {
        if (isset($args) && isset($args[0])) {
            $query = $args[0];

            // Use GoogleSearchClient if the API key is available
            if ($this->googleSearchClient->hasApiKey()) {
                $results = $this->googleSearchClient->search($query);
            } else {
                // Use DuckDuckGoSearchClient as a fallback when the Google API key isn't present
                $results = $this->duckDuckGoSearchClient->search($query);
            }

            $this->_results = $results;

            if (!empty($this->_results)) {
                $this->list();
            } else {
                $this->_response = "No search results were found for your query.";
            }
        } else {
            $this->_response = "Please provide a query to search for search results.";
            $this->_response .= $this->help();
        }
    }

    private function navigate($args)
    {
        $url = "";
        $response = "";
        if (isset($args[0])) {
            $number = $args[0];
            $number = (int)$number;
            $number = $number - 1;
            $result = $this->_results[$number];
            $url = $result['link'];
            $response .= "Opening {$url} in your default browser.";
        } else {
            $response .= "Please provide a number to open the corresponding search result.";
        }

        if ($url) {
            $processor = instance()->getDynamicInstance(\BlueFission\BlueCore\Command\CommandProcessor::class);
            $response .= "\n\n";
            $processor->process("open website {$url}");

            $response .= $processor->process('show website');
        }

        $this->_response = $response;
    }

    public function list()
    {
        $i = 0;
        $count = 0;
        $total = count($this->_results);
        $page = $this->_page;
        $totalPages = ceil($total / $this->_perPage);

        if ($page < 1) {
            $page = 1;
        } elseif ($page > $totalPages) {
            $page = $totalPages;
        }

        $pageStart = ($page - 1) * $this->_perPage;
        $pageEnd = $pageStart + $this->_perPage;

        $response = "Here are some search results related to your query:\n\n";
        foreach ($this->_results as $result) {
            if ($i >= $pageStart && $i < $pageEnd) {
                $number = $count + (($page*$this->_perPage) - $this->_perPage) + 1;
                $response .= "Number: {$number}\n";
                $response .= "{$result['title']}\n";
                $response .= "{$result['link']}\n";
                $response .= "{$result['snippet']}\n\n";
                $count++;
            }
            $i++;
        }
        $response .= "Showing {$count} of {$total} results. Page {$page} of {$totalPages}." . PHP_EOL;
        $response .= "Type `go to web <number>` to open the result." . PHP_EOL;
        $response .= "Type `previous web` or `next web` to move through pages." . PHP_EOL . PHP_EOL;
        $response .= "Which command do you want to run?" . PHP_EOL;

        $this->_response = rtrim($response);
    }

    public function next($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->list();
    }

    public function previous($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->list();
    }

    private function help(): string
    {
        return "Available commands for the Search Manager:\n" .
            "- search web for \"<query>\": Search for information related to the given query. Provide the query as an argument. Example: 'search web for \"apple\"'\n" .
            "- get web help: Show this help message.";
    }

    public function __destruct()
    {
        store('_system.search.page', $this->_page);
        store('_system.search.per_page', $this->_perPage);
        store('_system.search.results', $this->_results);
    }
}
