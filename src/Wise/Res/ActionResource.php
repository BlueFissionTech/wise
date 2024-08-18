<?php
namespace BlueFission\Wise\Res;

use BlueFission\Wise\Res\BaseResource;
use BlueFission\SimpleClients\OpenAIClient;
use BlueFission\Data\Storage\Disk;

class ActionResource extends BaseResource
{
    private $_storage;

    protected $_name = 'action';
    protected $_actions = ['run', 'create', 'generate', 'list', 'previous', 'next', 'show', 'delete', 'help'];

    public function __construct()
    {
        $this->_itemName = 'function';
        $this->_helpDetails['make'] = ["  - create: Create a new function with the given name, description, fields, and PHP code.", "      Usage: `create an action <name> that \"<description>\" with \"<jsonFormattedFields>\" by \"<code>\"`"];
        $this->_helpDetails['do'] = ["  - run: Execute the function with the given name, passing in the specified arguments.", "      Usage: `run the action <function name> with [\"<arguments>\"...]`"];
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
        $response = "Command '{$entry['name']}' details:\n";
        $response .= "  - Description: " . $entry['description'] . "\n";
        $response .= "  - Fields:\n";

        foreach ($entry['fields'] as $field) {
            $response .= "      - Name: " . $field['name'] . "\n";
            $response .= "        Type: " . $field['type'] . "\n";
            $response .= "        Description: " . $field['description'] . "\n";
        }

        $response .= "  - Code: \n" . $entry['code'] . "\n";

        return $response;
    }

    protected function makeItem($itemName, $args)
    {
        $key = $this->_key ?? null;
        $description = $args[1] ?? '';
        $fields = $args[2] ?? '';
        $code = $args[3] ?? '';

        if (!$key || !$description || !$fields || !$code ) {
            $this->_response = 'Custom function name, description, fields, and code must be provided.';
            return;
        }

        return [
            $key => $itemName,
            'description' => $description,
            'fields' => json_decode($fields, true),
            'code' => $code
        ];
    }

    protected function execute(array $args)
    {
        $actionName = $args[0] ?? '';
        $action = $this->retrieve($actionName);

        if (!$action) {
            $this->_response = "Command '$actionName' not found.";
            return;
        }

        var_dump($action);

        // Check if all required fields are present
        foreach ($action['fields'] as $field) {
            if (!isset($args[$field['name']])) {
                $this->_response = "Field '{$field['name']}' is required.";
                return;
            }
        }

        // Execute the action
        $output = '';
        $argsAssoc = [];
        foreach ($args as $name => $value) {
            $argsAssoc[$name] = $value;
        }

        extract($argsAssoc);
        eval($action['code']);
        $this->_response = $output;
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
        $gpt3_prompt = "Sample:\n$sampleJson\n\nTitle:\"$title\" \n\nCommand:\"$prompt\" \n\nUsing the example, generate a valid JSON file containing metadata and PHP code for the commmand: ";
        $gpt3_response = $openAIClient->complete($gpt3_prompt, ['max_tokens'=>3000]);

        // Check for errors in the response
        if (isset($gpt3_response['error'])) {
            $this->_response = "Error generating code.";
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

    private function jsonSample()
    {
        return '{
          "name": "Greet Command",
          "description": "Command to greet the user.",
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
        }';
    }
}