<?php
namespace BlueFission\BlueCore\Business\Commands;

use BlueFission\BlueCore\Command\BaseCommand;
use BlueFission\BlueCore\Business\Services\WikiNewsService;

class NewsCommand extends BaseCommand
{
    protected $_service;

    protected $_name = 'news';
    protected $_actions = ['get', 'find', 'select', 'help'];

    public function __construct(WikiNewsService $wikiNews)
    {
        $this->_service = $wikiNews;
        $this->_key = 'id';
        $this->_itemName = 'headline';
        $this->_helpDetails['get'] = ["  - get: Get the latest headlines from WikiNews by topic then location.", "      Usage: `get the news`", "      Usage: `get news about <topic> [in <location>]`"];
        $this->_helpDetails['find'] = ["  - find: Find the latest headlines from WikiNews by location then topic.", "      Usage: `find news from <location> [about <topic>]`"];
        parent::__construct();
    }

    protected function get($args)
    {
        $item = $this->itemName ?? $this->_name;

        $topic = $args['0'] ?? '';
        $location = $args['1'] ?? '';
        $this->_entries = $this->_service->getHeadlines($topic, $location);
        if (!empty($this->_entries)) {
            $i = 1;
            $entries = $this->_entries;
            $this->_entries = [];
            $response = "Here are the latest ".$this->pluralize($item).":\n";
            foreach ($entries as $entry) {
                $response .= "{$i}. {$entry['title']}\n";
                $entry['id'] = $i;
                $this->_entries[$i] = $entry;
                $i++;
            }
            $response .= "\n\nTo read a {$item}, type `select {$this->_name} <number>`";
        } else {
            $response = "No ".$this->pluralize($item)." found.\n\n";
            $response .= $this->help();
        }

        $this->_response = $response;
    }

    protected function find($args)
    {
        $location = $args['0'] ?? '';
        $topic = $args['1'] ?? '';
        $this->_entries = $this->_service->getHeadlines($topic, $location);
        if (!empty($this->_entries)) {
            $i = 1;
            $entries = $this->_entries;
            $this->_entries = [];
            $response = "Here are the latest headlines:\n";
            foreach ($entries as $headline) {
                $response .= "{$i}. {$headline['title']}\n";
                $headline['id'] = $i;
                $this->_entries[$i] = $headline;
                $i++;
            }
            $response .= "\n\nTo read an article, type `select news <number>`";
        } else {
            $response = "No headlines found.\n\n";
            $response .= $this->help();
        }

        $this->_response = $response;
    }
    
    protected function formatDetails($entry)
    {
        $response = "Here is the article:\n";
        $response .= "{$entry['title']}\n";
        $response .= date('l jS \of F Y h:i:s A', strtotime($entry['timestamp']))."\n";
        $snippet = str_replace(['<span class="searchmatch">','</span>'], ["[\e[36m","\e[0m]"], $entry['snippet']);
        $response .= "{$snippet}\n";

        return $response;
    }
}