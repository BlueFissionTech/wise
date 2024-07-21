<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Services\Service;
use BlueFission\Data\Storage\Disk;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\SimpleClients\OpenAIService;

class StepResource extends Service {

    private $_steps;
    private $_storage;

    public function __construct() {
        parent::__construct();

        $storagePath = OPUS_ROOT . '/storage/system';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $this->_storage = new Disk([
            'location' => $storagePath,
            'name' => 'steps_data.json',
        ]);
        $this->_storage->activate();

        $steps = $this->_storage->read();
        if ($steps) {
            $this->_steps = $steps;
        }
    }

    public function perform( ): IDispatcher
    {
        $args = func_get_args();
        $behavior = array_shift( $args );

        if ( Arr::is($behavior) ) {
            $behavior = array_shift($behavior);
        }

        $response = "";
        $methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $behavior))));
        if (method_exists($this, $methodName)) {
            $response .= $this->$methodName($args);
        } else {
            $response .=  "Invalid action";
        }

        $this->_response = $response;

        return $this;
    }

    private function generate(array $args) {
        // Check if the API key exists
        if (!env('OPEN_AI_API_KEY')) {
            return "No API key for the text generator found.";
        }

        try {
            // Initialize the OpenAIService
            $openAIService = new OpenAIService();

            $dialogue = instance()->service('convo')->generateRecentDialogueText(25);
            $json = $this->jsonSample();
            if ( !$dialogue ) {
                return "No dialogue avaiable to assess for goals.";
            }

            $charCount = strlen($dialogue);
            $threshold = 2500;
            if ($charCount > $threshold) {
                $charsToRemove = $charCount - $threshold;
                $dialogue = substr($dialogue, $charsToRemove);
            }

            $timestamp = $this->getCurrentTimeString();
            $input = "Conversation:\n$dialogue \n\n JSON:\n$json \n\n Current Time:\n$timestamp";

            // Use GPT-3 to get the prompt
            $gpt3_prompt = "\"$input\" \n\n Generate valid JSON in the identical format and same fields as the example except with descriptions of the User's goal, replace the steps with ones to complete the particular goal, and add the next immediate action to take as presented in the conversation. \n\nJSON: ";
            $gpt3_response = $openAIService->complete($gpt3_prompt, ['max_tokens'=>1000]);

            // Check if there are errors in the response
            if (isset($gpt3_response['error'])) {

                return "There was an error generating steps.";
            }

            // Get the completion
            $steps = trim($gpt3_response['choices'][0]['text']) ?? '{}';
            if ($this->validateJSON($steps)) {

                $steps = preg_replace('/[[:cntrl:]]/', '', $steps);
                $this->_steps = json_decode($steps, true);
                $this->saveData();
                $response = "Steps generated successfully.\n";
                $response .= $this->show()."\n";
                $response .= $this->get()."\n";
                $response .= "Use your todo and schedule to organize activities.\n";

                return $response;
            } else {
                return "Generated steps were malformed. Try again later.";
            }


        } catch (Exception $e) {
            return "Error generating steps.";
        }
    }

    private function update(array $args) {
        $currentTime = new \DateTime();

        if (!isset($this->_steps)) {
            // return $this->generate($args);
            $this->cancel();
        }
        
        // Check expiration time for goal, steps, and action
        $goalExpiration = new \DateTime($this->_steps['goal']['last_changed']);
        $goalExpiration->modify('+30 minutes');
        
        $activeStep = $this->getActiveStep();
        
        $stepsExpiration = new \DateTime($activeStep['last_changed']);
        $stepsExpiration->modify('+10 minutes');
        
        $actionExpiration = new \DateTime($this->_steps['goal']['action']['last_changed']);
        $actionExpiration->modify('+1 minute');

        // Update the expired information
        if ($currentTime > $goalExpiration) {
            $this->_steps['goal']['description'] = $this->generateNewGoal();
            $this->_steps['goal']['last_changed'] = $currentTime->format('c');
        }

        if ($currentTime > $stepsExpiration) {
            $this->_steps['goal']['steps'] = $this->generateNewSteps();
            foreach ($this->_steps['goal']['steps'] as &$step) {
                $step['last_changed'] = $currentTime->format('c');
            }
        }

        if ($currentTime > $actionExpiration) {
            $this->_steps['goal']['action']['description'] = $this->generateNewAction();
            $this->_steps['goal']['action']['last_changed'] = $currentTime->format('c');
        }

        // Save the updated data
        $this->saveData();

        $response = "Steps updated successfully! Use `list step` to see your goal itinerary!\n";
        $response .= $this->show()."\n";
        $response .= $this->get()."\n";
        $response .= "Use your todo and schedule to organize activities.\n";

        return $response;
    }


    private function list() {
        if (!isset($this->_steps)) {
            return "No steps have been generated yet.";
        }
        $output = "Steps:\n";
        foreach ($this->_steps['goal']['steps'] as $index => $step) {
            $status = $step['complete'] ? 'Complete' : 'Incomplete';
            $output .= ($index + 1) . ". " . $step['description'] . " - " . $status . "\n";
        }
        return $output;
    }

    private function show() {
        if (!isset($this->_steps)) {
            return "There is currently no goal or steps to display. `generate steps` to generate a new goal.";
        }
        return "Current goal: " . isset($this->_steps['goal']['description']) ? $this->_steps['goal']['description'] : $this->_steps->goal->description;
    }

    private function get() {
        if (!isset($this->_steps)) {
            return "There is currently no action to display yet.";
        }
        return "Current action: " . isset($this->_steps['goal']['action']) ? $this->_steps['goal']['action']['description'] : $this->_steps->goal->action->description;

    }

    private function set($args) {
        if (!isset($this->_steps)) {
            return "There is currently no action to update yet.";
        }

        if (isset($args[0])) {
            $newAction = $args[0];
            $this->_steps['goal']->action->description = $newAction;
            $this->_steps['goal']->action->last_changed = $this->getCurrentTimeString();
            $this->saveData();
            return "Action has been set to: " . $newAction;
        } else {
            return "Please provide a new action description.";
        }
    }

    private function next() {
        if (!isset($this->_steps)) {
            return "There are currently no goals or steps.";
        }


        $found = false;
        for ($i = 0; $i < count($this->_steps['goal']->steps); $i++) {
            if (!$this->_steps['goal']->steps[$i]->complete && !$found) {
                $this->_steps['goal']->steps[$i]->complete = true;
                $this->_steps['goal']->steps[$i]->last_changed = $this->getCurrentTimeString();
                $found = true;
            } elseif ($found) {
                return "Moved to the next step: " . $this->_steps['goal']['steps'][$i]['description'];
            }
        }

        if (!$found) {
            return "All steps are already complete.";
        } else {
            return "Moved to the final step.";
        }
    }


    private function previous() {
        if (!isset($this->_steps)) {
            return "There are currently no goals or steps.";
        }
        $found = false;
        for ($i = count($this->_steps['goal']['steps']) - 1; $i >= 0; $i--) {
            if ($this->_steps['goal']['steps'][$i]->complete && !$found) {
                $this->_steps['goal']['steps'][$i]->complete = false;
                $this->_steps['goal']['steps'][$i]->last_changed = $this->getCurrentTimeString();
                $found = true;
            } elseif ($found) {
                return "Moved back to the previous step: " . $this->_steps['goal']['steps'][$i]->description;
            }
        }

        if (!$found) {
            return "All steps are already incomplete.";
        } else {
            return "Moved back to the first step.";
        }
    }

    private function add($args) {
        if (!isset($this->_steps)) {
            return "There are currently no goals or steps.";
        }


        $description = $args[0] ?? "";
        $position = isset($args[1]) && is_numeric($args[1]) ? intval($args[1]) : count($this->_steps['goal']['steps']);

        if ($description === "") {
            return "Step description cannot be empty.";
        }

        $newStep = new \stdClass();
        $newStep->description = $description;
        $newStep->last_changed = $this->getCurrentTimeString();
        $newStep->complete = false;

        array_splice($this->_steps['goal']['steps'], $position, 0, [$newStep]);

        $this->saveData();
        return "Step added at position " . ($position + 1) . ": " . $description;
    }


    private function delete($args) {
        if (!isset($this->_steps)) {
            return "There are currently no goals or steps.";
        }
        $position = isset($args[0]) && is_numeric($args[0]) ? intval($args[0]) - 1 : $this->getCurrentStepIndex();

        if ($position < 0 || $position >= count($this->_steps['goal']['steps'])) {
            return "Invalid step position.";
        }

        $removedStep = $this->_steps['goal']['steps'][$position];
        array_splice($this->_steps['goal']['steps'], $position, 1);

        $this->saveData();
        return "Step removed: " . $removedStep['description'];
    }

    private function generateNewGoal() {
        // Check if the API key exists
        if (!env('OPEN_AI_API_KEY')) {
            return '';
        }

        try {
            // Initialize the OpenAIService
            $openAIService = new OpenAIService();

            // Get the recent conversation dialogue for context
            $dialogue = instance()->service('convo')->generateRecentDialogueText(25);

            // Build prompt
            $prompt = "$dialogue\n\nCreate a new goal to solve the user's needs:";

            // Use GPT-3 to generate a new goal
            $response = $openAIService->complete($prompt);

            // Check if there are errors in the response
            if (isset($response['error'])) {
                return '';
            }

            // Get the generated goal
            $goal = trim($response['choices'][0]['text']);

            return $goal;
        } catch (Exception $e) {
            return '';
        }
    }

    private function generateNewAction() {
        // Check if the API key exists
        if (!env('OPEN_AI_API_KEY')) {
            return;
        }

        try {
            // Initialize the OpenAIService
            $openAIService = new OpenAIService();

            // Get the most recent dialogue and goal description
            $dialogue = instance()->service('convo')->generateRecentDialogueText(25);
            $goal = $this->_steps['goal']['description'];
            $position = $this->getCurrentStepIndex();
            $step = $this->_steps['goal']['steps'][$position]['description'];

            // Use GPT-3 to generate a new action
            $prompt = "Given the following conversation, goal, and task define the next immediate action to complete the goal:\n\n$dialogue\n\nGoal: $goal\n\Task: $step\n\nAction:";
            $gpt3_response = $openAIService->complete($prompt);

            // Check if there are errors in the response
            if (isset($gpt3_response['error'])) {
                return "There has been an error generating the next action.";
            }

            // Get the completion
            $action = trim($gpt3_response['choices'][0]['text']);

            return $action;
        } catch (Exception $e) {
            return "the system has encountered an error generating the next action.";
        }
    }

    private function generateNewSteps() {
        // Check if the API key exists
        if (!env('OPEN_AI_API_KEY')) {
            return [];
        }

        try {
            // Initialize the OpenAIService
            $openAIService = new OpenAIService();

            $prompt = ($this->_steps['goal']['description'] ?? $this->_steps['goal']->description) . "\n\n";
            $prompt .= "Create a list of steps to achieve the goal: ";

            // Use GPT-3 to get the prompt
            $gpt3_response = $openAIService->complete($prompt);

            // Check if there are errors in the response
            if (isset($gpt3_response['error'])) {
                return [];
            }

            // Get the completion
            $completion = trim($gpt3_response['choices'][0]['text']);

            // Parse the completion into individual steps
            $rawSteps = explode("\n", $completion);
            $steps = [];

            foreach ($rawSteps as $rawStep) {
                $description = trim(str_replace(['-', '–'], '', $rawStep));
                if (!empty($description)) {
                    $steps[] = [
                        'description' => $description,
                        'last_changed' => $this->getCurrentTimeString(),
                        'complete' => false
                    ];
                }
            }

            return $steps;
        } catch (Exception $e) {
            return [];
        }
    }

    private function getCurrentTimeString() {
        return (new \DateTime())->format('c');
    }

    private function getCurrentStepIndex() {
        foreach ($this->_steps['goal']['steps'] as $index => $step) {
            if (!$step['complete']) {
                return $index;
            }
        }

        return count($this->_steps['goal']['steps']);
    }

    private function getActiveStep() {
        $index = $this->getCurrentStepIndex();
        return $index < count($this->_steps['goal']['steps']) ? $this->_steps['goal']['steps'][$index] : null;
    }

    private function cancel() {
        $this->_steps['goal'] = [
            'description' => '',
            'last_changed' => '',
            'steps' => [],
            'action' => [
                'description' => '',
                'last_changed' => ''
            ]
        ];

        $this->_steps = json_decode($this->jsonSample(), true);

        $this->saveData();
        return "Goal canceled and all fields emptied.";
    }

    private function help() {
        return "Available commands:\n\n" .
               "generate steps - Creates a new goal object based on the current conversation.\n" .
               "update steps- Updates information that has expired (goal, steps, or action).\n" .
               "list steps - Displays all the steps in order along with their status.\n" .
               "show step- Displays the current goal description fully.\n" .
               "get step- Gets the current action.\n" .
               "set step <action> - Changes the current action to the provided action.\n" .
               "next step - Moves on to the next step and completes the previous one.\n" .
               "previous step - Goes back to the previous step and sets it as incomplete again.\n" .
               "add step <description> [<index>] - Adds a new step to the list with an optional index.\n" .
               "delete step [<index>] - Removes a step, by default the current step, or the step at the specified index.\n" .
               "cancel steps - Cancels the current goal and empties the JSON fields.\n" .
               "help step - Displays a verbose and clear description of all the commands.\n";
    }

    private function jsonSample()
    {
        $sample = '{
          "goal": {
            "description": "Generate a new goal to solve the user\'s needs",
            "last_changed": "2023-04-20T08:30:00Z",
            "steps": [
              {
                "description": "Inquire about the user\'s goals",
                "last_changed": "2023-04-20T08:32:00Z",
                "complete": false
              },
              {
                "description": "Ask open ended questions about the user\'s goals",
                "last_changed": "2023-04-20T08:33:00Z",
                "complete": false
              },
              {
                "description": "Figure out what measurements define the user\'s goal",
                "last_changed": "2023-04-20T08:35:00Z",
                "complete": false
              },
              {
                "description": "Create a list of steps to achieve the goal",
                "last_changed": "2023-04-20T08:40:00Z",
                "complete": false
              },
              {
                "description": "Create a plan to procure any needed resources",
                "last_changed": "2023-04-20T08:45:00Z",
                "complete": false
              }
            ],
            "action": {
              "description": "First immediate action to address the first step...",
              "last_changed": "2023-04-20T08:50:00Z"
            }
          }
        }';

        return $sample;
    }

    private function saveData() {
        $this->_storage->contents(json_encode($this->_steps));
        $this->_storage->assign($this->_steps);
        $this->_storage->write();
    }

    private function validateJSON(string $json): bool {
        try {
            $test = json_decode($json, null, flags: JSON_THROW_ON_ERROR);
            return true;
        } catch  (Exception $e) {
            return false;
        }
    }
}