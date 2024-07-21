<?php

// TutorialCommand.php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\SimpleClients\WikiHowService;

class HowToResource extends Service
{
    private $wikiHowService;

    public function __construct(WikiHowService $wikiHowService)
    {
        $this->wikiHowService = $wikiHowService;
        parent::__construct();
    }

    public function search($behavior, $args)
    {
        if (isset($args) && isset($args[0])) {
            $query = $args[0];
            $results = $this->wikiHowService->search($query);

            if (!empty($results)) {
                $response = "Here are some WikiHow tutorials related to your query:\n\n";
                foreach ($results as $result) {
                    $response .= "{$result['title']} (Rating: {$result['rating']})\n";
                    $response .= "{$result['description']}\n";
                    $response .= "URL: {$result['url']}\n\n";
                }
                $this->_response = rtrim($response);
            } else {
                $this->_response = "No WikiHow tutorials were found for your query.";
            }
        } else {
            $this->help($behavior, $args);
            $this->_response .= "\nPlease provide a query to search for WikiHow tutorials. ex: find a howto about <your task here>";

        }
    }

    public function show ($behavior, $args)
    {
        if (isset($args) && isset($args[0])) {
            $url = $args[0];
            $steps = $this->wikiHowService->getStepsAsString($url);

            if (!empty($steps)) {
                $this->_response = "Here are the steps for the WikiHow tutorial:\n\n{$steps}";
            } else {
                $this->_response = "No steps were found for the provided WikiHow tutorial.";
            }
        } else {
            $this->help($behavior, $args);
            $this->_response .= "\nPlease provide a URL to show a WikiHow tutorial.";
        }
    }

    public function help($behavior, $args)
    {
        $help = "HowToCommand Help:\n\n";
        $help .= "1. find a howto about \"<query>\": Searches for WikiHow tutorials related to your query.\n";
        $help .= "2. show the howto <url>: Displays the steps for the provided WikiHow tutorial URL.\n";

        $this->_response = $help;
    }

}
