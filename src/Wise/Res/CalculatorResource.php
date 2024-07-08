<?php
namespace BlueFission\Wise\Res;

use BlueFission\BlueCore\Command\BaseResource;
use BlueFission\Data\Storage\Disk;
use BlueFission\SimpleClients\OpenAIService;
use BlueFission\BlueCore\Business\Prompts\CreateMathExpression;

class CalculatorResource extends BaseResource
{
    private $_storage;

    protected $_name = 'calc';
    protected $_actions = ['run', 'help'];

    public function __construct()
    {
        $this->_helpDetails['do'] = ["  - run: Evaluates the numeric mathematical expression and returns the result.", "      Usage: `run calc on \"<expression>\"`"];

        parent::__construct();
    }

    protected function execute($args) {
        $expression = implode('', $args);
        if ($this->isValidMathExpression($expression)) {
            $result = eval("return $expression;");
        } else {
            $expression = $this->generate($args);
            // remove commas, spaces, and uncalculable characters
            // $expression = preg_replace('/[^\d\+\-\*\/\.\(\)]+/', '', $expression);
            $expression = str_replace(['x = ', '÷', '×', '^', ','], ['', '/', '*', '**', ''], $expression);
            $result = eval("return $expression;");
        }
        $this->_response = $result;
    }

    protected function generate($args) {
        $expression = implode('', $args);

        // Check if the API key exists
        if (!env('OPEN_AI_API_KEY')) {
            return $expression;
        }

        try {
            // Initialize the OpenAIService
            $openAIService = new OpenAIService();
            
            // die(var_dump($expression));

            $prompt = (new CreateMathExpression($expression))->prompt();
            $gpt3_response = $openAIService->complete($prompt, ['max_tokens'=>50, 'stop'=>[]]);

            // Check if there are errors in the response
            if (isset($gpt3_response['error'])) {

                return "There was an error generating steps.";
            }

            // Get the completion
            $expression = trim($gpt3_response['choices'][0]['text']) ?? 0;

            return $expression;
        } catch (Exception $e) {
            return $expression;
        }
    }

    private function isValidMathExpression($expression) {
        // Check if the expression contains only valid characters (digits, operators, parentheses, and whitespace)
        if (!preg_match('/^[\d\s\+\-\*\/\.\(\)]+$/', $expression)) {
            return false;
        }

        // Replace division and multiplication symbols with their PHP equivalents
        $expression = str_replace(['÷', '×', '^', ','], ['/', '*', '**', ''], $expression);

        // Use a try-catch block to handle potential errors when evaluating the expression
        try {
            $result = null;
            $evaluatedExpression = @eval("\$result = $expression;");
            return $evaluatedExpression !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}