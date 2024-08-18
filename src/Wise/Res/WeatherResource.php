<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\SimpleClients\OpenWeatherClient;

class WeatherResource extends BaseResource
{
    protected $_client;

    protected $_name = 'weather';
    protected $_actions = ['get', 'help'];

    public function __construct(OpenWeatherClient $openWeather)
    {
        $this->_client = $openWeather;
        $this->_helpDetails['get'] = ["  - get: Get the current weather for a specified location.", "      Usage: `get the weather in <location>`"];
        parent::__construct();
    }

    protected function get($args)
    {
        if (count($args) >= 1) {
            $location = $args[0] ?? "";
            $response = $this->_client->getWeatherByLocation($location);
        } else {
            $response = "Please provide a location. For example: `get the weather in \"New York\"`";
        }

        $this->_response = $response;
    }
}