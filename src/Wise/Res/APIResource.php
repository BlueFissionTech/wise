<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\SimpleClients\OpenAIClient;
use BlueFission\Data\Storage\Disk;
use \BlueFission\Connections\Curl;

class APIResource extends BaseResource
{
    private $_storage;

    protected $_name = 'api';
    protected $_actions = ['use', 'create', 'generate', 'list', 'previous', 'next', 'show', 'delete', 'help'];

    public function __construct()
    {
        $this->_itemName = 'remote call';
        $this->_helpDetails['make'] = ["  - create: Create a new api call with the given name, description, and parameters.", "      Usage: `create api <name> \"<description>\" by <method> at <url> with \"<params>\" \"<code>\"`"];
        $this->_helpDetails['do'] = ["  - use: Invoke the api call with the given name, passing in the specified parameters.", "      Usage: `use the api <api call name> with [\"<parameters>\"...]`"];
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

    protected function formatDetails($entry)
    {
        $response = "API Command '{$entry[$this->_key]}':\nDescription: {$entry['description']}\nMethod: {$entry['method']}\nURL: {$entry['url']}\nParams: " . json_encode($entry['params']) . "\nCode: {$entry['code']}";

        return $response;
    }

    protected function execute(array $args)
    {
        $apiName = $args[0] ?? '';
        $params = $args[1] ?? [];

        if (!$apiName) {
            $this->_response = "You must provide the name of the API command to call.";
            return;
        }

        $apiCommands = $this->_storage->read() ?? [];

        if (!isset($apiCommands[$apiName])) {
            $this->_response = "API command '$apiName' not found.";
            return;
        }

        $apiCommand = $apiCommands[$apiName];

        // Replace placeholders in params with values from $args
        $apiParams = [];
        foreach ($apiCommand['params'] as $key => $value) {
            if (isset($params[$key])) {
                $apiParams[$key] = $params[$key];
            } else {
                $apiParams[$key] = $value;
            }
        }

        $output = $this->invoke($apiCommand['method'], $apiCommand['url'], $apiParams, $apiCommand['code']);
        $this->_response = $output;
    }

    protected function makeItem($itemName, $args)
    {
        $name = $args[0] ?? '';
        $description = $args[1] ?? '';
        $method = $args[2] ?? '';
        $url = $args[3] ?? '';
        $params = $args[4] ?? '';
        $code = $args[5] ?? '';

        if (!$name || !$description || !$method || !$url || !$params || !$code) {
            $this->_response = 'API command name, description, method, url, params, and code must be provided.';
            return;
        }

        return [
            $this->_key => $itemName,
            'description' => $description,
            'method' => $method,
            'url' => $url,
            'params' => $params,
            'code' => $code,
        ];
    }

    protected function generate($args)
    {
        $title = $args[0] ?? '';
        $prompt = $args[1] ?? '';

        // Check if the OpenAI API key is set
        if (!env('OPEN_AI_API_KEY')) {
            $this->_response = "OpenAI API key is not set.";
            return;
        }

        $sampleJson = $this->jsonSample();

        // Initialize the OpenAIClient
        $openAIClient = new OpenAIClient();

        // Generate the code using GPT-3
        $gpt3_prompt = "Sample:\n$sampleJson\n\nTitle:\"$title\" \n\nCommand:\"$prompt\" \n\nUsing the example, generate a JSON file representing the API call and the PHP to consume the response: ";
        $gpt3_response = $openAIClient->complete($gpt3_prompt, ['max_tokens'=>3000]);

        // Check for errors in the response
        if (isset($gpt3_response['error'])) {
            $this->_response = "Error generating api.";
            return;
        }

        // Get the generated code
        $code = trim($gpt3_response['choices'][0]['text']);

        $this->_response = $code;
    }

    protected function refreshList()
    {
        $this->_entries = $this->_storage->read() ?? [];
    }

    protected function store()
    {
        $this->_storage->contents(json_encode($this->_entries));
        $this->_storage->assign($this->_entries);
        $this->_storage->write();
    }

    private function invoke($method, $url, $params, $code) 
    {
    // Instantiate your Curl class
        $client = new Curl();

        try {
            // Set configuration data
            $config = [
                'target' => $url,
                'method' => $method,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ];

            // Configure the Curl client
            $client->config($config);

            // Open the Curl connection
            $client->open();

            // Execute the request using the Curl client
            $client->query($params);

            // Get the response body and status code
            $responseBody = $client->_result;
            // $statusCode = $client->status(); // assuming the 'status' method in your Curl class returns the status code

            $responseData = json_decode($responseBody, true);

            // Execute user-defined code to handle the response data
            $output = '';
            eval($code);

            // Close the Curl connection
            $client->close();

            return $output;
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    private function jsonSample()
    {
        return '{
            "api": {
                "name": "Greeting API",
                "version": "1.0",
                "baseUrl": "https://api.example.com/v1"
            },
            "endpoints": [
                {
                    "name": "Greet Command",
                    "description": "Command to greet the user.",
                    "path": "/greet",
                    "method": "POST",
                    "fields": [
                        {
                            "name": "greeting",
                            "type": "string",
                            "description": "The greeting message to display."
                        },
                        {
                            "name": "name",
                            "type": "string",
                            "description": "The name of the person to greet."
                        }
                    ],
                    "code": "echo $args[\'greeting\'] . \', \' . $args[\'name\'] . \'!\';"
                }
            ]
        }';
    }
}