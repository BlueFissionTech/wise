<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\SimpleClients\WikipediaClient;

class EncyclopediaResource extends BaseResource
{
    private $_storage;

    protected $_name = 'info';
    protected $_actions = ['get', 'help'];
    protected $_plural = 'info';
    private $_client;

    public function __construct(WikipediaClient $wikipediaClient)
    {
        $this->_helpDetails['get'] = ["  - get: Looks up the specified term on Wikipedia and returns a summary.", "      Usage: `get info about \"<term>\"`"];
        $this->_client = $wikipediaClient;
        parent::__construct();
    }

    protected function get($args)
    {
        if (isset($args) && isset($args[0])) {
            $topic = $args[0];
            $summary = $this->_client->getSummary($topic);
            $this->_response = $summary;
        } else {
            $this->_response = "Please provide a topic for the Wikipedia lookup (`get info about \"<term>\"`).";
            $this->_response .= $this->help();
        }
    }
}