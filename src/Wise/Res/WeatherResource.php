<?php
namespace BlueFission\Wise\Res;

use BlueFission\BlueCore\Command\BaseResource;
use BlueFission\SimpleClients\OpenWeatherService;

class WeatherResource extends BaseResource
{
    protected $_service;

    protected $_name = 'weather';
    protected $_actions = ['get', 'help'];

    public function __construct(OpenWeatherService $openWeather)
    {
        $this->_service = $openWeather;
        $this->_helpDetails['get'] = ["  - get: Get the current weather for a specified location.", "      Usage: `get the weather in <location>`"];
        parent::__construct();
    }

    protected function get($args)
    {
        if (count($args) >= 1) {
            $location = $args[0] ?? "";
            $response = $this->_service->getWeatherByLocation($location);
        } else {
            $response = "Please provide a location. For example: `get the weather in \"New York\"`";
        }

        $this->_response = $response;
    }
}