<?php
namespace BlueFission\Wise\Res;

use BlueFission\SimpleClients\HuggingFaceClient;
use BlueFission\Data\Storage\Disk;
use BlueFission\Services\Service;
use BlueFission\DevValue;

class AIResource extends Service
{
    private $_storage;
    private $_huggingFaceClient;

    public function __construct()
    {
        parent::__construct();
        $this->_huggingFaceClient = new HuggingFaceClient();
        $this->_storage = new Disk();
    }

    public function handle($behavior, $args)
    {
        $action = $behavior->name();
        $this->_response = "Invalid action specified.";

        if (!$this->_huggingFaceClient->hasApiKey()) {
            $this->_response = "API Key not found for HuggingFaceClient. Please set your API key in the .env file.";
            return;
        }

        switch ($action) {
            case 'find':
                $result = $this->find($args);
                $this->_response = $this->formatDatasetList($result);
                break;
            case 'get':
                $result = $this->get($args);
                $this->_response = $this->formatDatasetDetails($result);
                break;
            case 'do':
                $result = $this->useSpace($args);
                $this->_response = $this->formatModelOutput($result);
                break;
            case 'list':
                $result = $this->listSpaces($args);
                $this->_response = $this->formatSpaceList($result);
                break;
            case 'show':
                $result = $this->showSpace($args);
                $this->_response = $this->formatSpaceDetails($result);
                break;
            default:
                $this->_response = $this->help();
                // throw new \InvalidArgumentException("Invalid action specified.");
        }
    }

    public function list($args)
    {
        $searchTerm = $args[0] ?? '';
        return $this->_huggingFaceClient->listModels($searchTerm);
    }

    public function show($args)
    {
        $modelId = $args[0] ?? '';
        return $this->_huggingFaceClient->getModelDetails($modelId);
    }

    public function find($args)
    {
        $searchTerm = $args[0] ?? '';
        return $this->_huggingFaceClient->findDatasets($searchTerm);
    }

    public function get($args)
    {
        $datasetId = $args[0] ?? '';
        return $this->_huggingFaceClient->getDatasetDetails($datasetId);
    }

    public function useModel($args)
    {
        $modelId = $args[0] ?? '';
        $inputText = $args[1] ?? '';
        return $this->_huggingFaceClient->useModel($modelId, $inputText);
    }

    public function useSpace($args)
    {
        $spaceId = $args[0] ?? '';
        $inputText = $args[1] ?? '';
        return $this->_huggingFaceClient->useSpace($spaceName, $method, $data, $queryParams);
    }

    public function help()
    {
        $response = "AIManager commands:\n";
        $response .= "- list: List all available models on Hugging Face.\n";
        $response .= "  Usage: list ai for \"<search term>\" (optional)\n";
        $response .= "- show: Show details for a specific model on Hugging Face.\n";
        $response .= "  Usage: show ai <modelId>\n";
        $response .= "- find: Find all available datasets on Hugging Face.\n";
        $response .= "  Usage: find from ai by \"<search term>\" (optional)\n";
        $response .= "- get: Get details for a specific dataset on Hugging Face.\n";
        $response .= "  Usage: get ai <datasetId>\n";
        $response .= "- use: Run a specific model with given input text.\n";
        $response .= "  Usage: use ai <modelId> with \"<inputText>\"\n";

        return $response;
    }

    private function formatModelList($models)
    {
        $response = "AI Model List:\n";

        usort($models, function($a, $b) {
            // Sort by 'downloads' in descending order
            $downloadsDiff = $b['downloads'] - $a['downloads'];
            
            // If 'downloads' are equal, sort by 'likes' in descending order
            if ($downloadsDiff == 0) {
                $likesDiff = $b['likes'] - $a['likes'];

                // If 'likes' are equal, sort by 'lastModified' in descending order
                if ($likesDiff == 0) {
                    return strtotime($b['lastModified']) - strtotime($a['lastModified']);
                }

                return $likesDiff;
            }
            
            return $downloadsDiff;
        });

        foreach ($models as $model) {
            $tags = DevValue::truncate(implode(' ', $model['tags']), 30);
            $tags = $tags ? "($tags)" : '';
            $response .= "  - {$model['id']} $tags\n";
        }
        return $response;
    }

    private function formatModelDetails($model)
    {
        $response = "AI Model Details:\n";
        $response .= "  ID: {$model['id']}\n";
        $response .= "  Tags: ".implode(', ', $model['tags'])."\n";
        
        if (isset($model['description'])) {
            $response .= "  Description: {$model['description']}\n";
        }
        
        if (isset($model['model-index'])) {
            $response .= "  Model Index:\n";
            foreach ($model['model-index'] as $index) {
                $response .= "    Task: {$index['task']['name']} ({$index['task']['type']})\n";
                $response .= "    Dataset: {$index['dataset']['name']} ({$index['dataset']['type']})\n";
                $response .= "    Metrics:\n";
                foreach ($index['metrics'] as $metric) {
                    $response .= "      {$metric['name']}: {$metric['value']}\n";
                }
            }
        }

        // Add more fields if needed
        return $response;

    }

    private function formatDatasetList($datasets)
    {
        $response = "Dataset List:\n";

        usort($datasets, function($a, $b) {
            // Sort by 'downloads' in descending order
            $downloadsDiff = $b['downloads'] - $a['downloads'];
            
            // If 'downloads' are equal, sort by 'likes' in descending order
            if ($downloadsDiff == 0) {
                $likesDiff = $b['likes'] - $a['likes'];

                // If 'likes' are equal, sort by 'lastModified' in descending order
                if ($likesDiff == 0) {
                    return strtotime($b['lastModified']) - strtotime($a['lastModified']);
                }

                return $likesDiff;
            }
            
            return $downloadsDiff;
        });

        foreach ($datasets as $dataset) {
            $tags = DevValue::truncate(implode(' ', $dataset['tags']), 30);
            $tags = $tags ? "($tags)" : '';
            $response .= "  - {$dataset['id']} $tags\n";
        }
        return $response;
    }

    private function formatDatasetDetails($dataset)
    {
        $response = "Dataset Details:\n";
        $response .= "  ID: {$dataset['id']}\n";
        $response .= "  Tags: ".implode(', ', $dataset['tags'])."\n";
        
        if (isset($dataset['description'])) {
            $response .= "  Description: {$dataset['description']}\n";
        }
        // Add more fields if needed
        return $response;
    }

    private function formatModelOutput($output)
    {
        // Assuming the output is a string, modify as needed based on the actual structure
        return $output;
    }

    public function listSpaces($args)
    {
        $searchTerm = $args[0] ?? '';
        return $this->_huggingFaceClient->findSpaces($searchTerm);
    }

    public function showSpace($args)
    {
        $spaceName = $args[0] ?? '';
        return $this->_huggingFaceClient->getSpaceDetails($spaceName);
    }

    public function findSpacesByModel($args)
    {
        $modelId = $args[0] ?? '';
        return $this->_huggingFaceClient->findSpacesByModel($modelId);
    }

    private function formatSpaceList($spaces)
    {
        $response = "Hosted AI Model Space List:\n";

        usort($spaces, function($a, $b) {
            $likesDiff = $b['likes'] - $a['likes'];

            // If 'likes' are equal, sort by 'lastModified' in descending order
            if ($likesDiff == 0) {
                return strtotime($b['lastModified']) - strtotime($a['lastModified']);
            }

            return $likesDiff;
        });

        foreach ($spaces as $space) {
            $tags = DevValue::truncate(implode(' ', $space['tags']), 30);
            $tags = $tags ? "($tags)" : '';
            $response .= "  - {$space['id']} $tags\n";
        }
        return $response;
    }

    private function formatSpaceDetails($space)
    {
        $response = "Hosted AI Model Space Details:\n";
        if (isset($space['name'])) {
            $response .= "  Name: {$space['name']}\n";
        } elseif (isset($space['cardData']) && isset($space['cardData']['name'])) {
            $response .= "  Name: {$space['cardData']['name']}\n";
        } elseif (isset($space['cardData']) && isset($space['cardData']['title'])) {
            $response .= "  Name: {$space['cardData']['title']}\n";
        }

        $response .= "  Tags: ".implode(', ', $space['tags'])."\n";
                
        if (isset($space['description'])) {
            $response .= "  Description: {$space['description']}\n";
        }

        if (isset($space['host'])) {
            $response .= "  URL: {$space['host']}\n";
        }
        
        if (isset($space['endpoints'])) {
            $response .= "  Endpoints:\n";
            foreach ($space['endpoints'] as $endpoint) {
                $response .= "    - {$endpoint['path']} ({$endpoint['method']})\n";
                if (isset($endpoint['description'])) {
                    $response .= "      Description: {$endpoint['description']}\n";
                }
            }
        }

        // Add more fields if needed
        return $response;
    }

}
