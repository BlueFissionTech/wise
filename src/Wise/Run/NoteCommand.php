<?php

namespace BlueFission\BlueCore\Business\Commands;

use BlueFission\BlueCore\Business\Prompts\MakeNote;
use BlueFission\BlueCore\Business\Services\OpenAIService;
use BlueFission\Data\Storage\Disk;
use BlueFission\Services\Service;

class NoteCommand extends Service
{
    private $_storage;
    private $_page = 1;
    private $_perPage = 5;

    public function __construct()
    {
        parent::__construct();

        $storagePath = OPUS_ROOT . '/storage/system';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $this->_storage = new Disk([
            'location' => $storagePath,
            'name' => 'note_data.json',
        ]);
        $this->_storage->activate();
    }

    public function handle($behavior, $args)
    {
        $action = $behavior->name();

        switch ($action) {
            case 'make':
                $this->create($args);
                break;

            case 'save':
                $this->saveNote($args);
                break;

            case 'list':
                $this->listNotes($args);
                break;

            case 'get':
                $this->getNoteByName($args);
                break;

            case 'find':
                $this->searchNotes($args);
                break;

            case 'next':
                $this->next($args);
                break;

            case 'previous':
                $this->previous($args);
                break;

            case 'help':
                $this->help();
                break;

            default:
                $this->_response = "Invalid command. Type `help` to see available commands.\n\n";
                $this->help();
                break;
        }
    }

    public function saveNote(array $args)
    {
        $title = $args[0] ?? '';
        $note = $args[1] ?? '';

        if (!$title || !$note) {
            $this->_response = "Please provide both title and note.";
            return;
        }

        $data = $this->_storage->read() ?? [];

        if (!isset($data['notes'])) {
            $data['notes'] = [];
        }

        // Check if the note already exists, and if so, append the new note onto the next line
        if (isset($data['notes'][$title])) {
            $data['notes'][$title] .= PHP_EOL . $note;
        } else {
            $data['notes'][$title] = $note;
        }

        $this->_storage->contents(json_encode($data));
        $this->_storage->assign($data);
        $this->_storage->write();

        $this->_response = "Note saved successfully.";
    }

    public function create(array $args)
    {
        $input = $args[0] ?? '';

        // Check if the OpenAI API key is set
        if (!env('OPEN_AI_API_KEY')) {
            $this->_response = "OpenAI API key is not set.";
            return;
        }

        $dialogue = instance()->service('convo')->generateRecentDialogueText(25);

        // Initialize the OpenAIService
        $openAIService = new OpenAIService();

        // Generate the code using GPT-3
        $prompt = new MakeNote($input, $dialogue);
        $gpt3_response = $openAIService->chat($prompt->prompt(), ['max_tokens'=>1000]);

        // Check for errors in the response
        if (isset($gpt3_response['error'])) {
            $this->_response = "Error generating note.";
            return;
        }

        // Get the generated code
        $note = trim($gpt3_response['choices'][0]['message']['content']);

        $this->_response = $note;

        $data = $this->_storage->read() ?? [];

        if (!isset($data['notes'])) {
            $data['notes'] = [];
        }

        $data['notes'][$input] = $note;

        $this->_storage->contents(json_encode($data));
        $this->_storage->assign($data);
        $this->_storage->write();
    }

    public function listNotes(array $args)
    {
        $page = count($args) >= 1 ? (int) $args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int) $args[0];
            $page = (int) $args[1];
        }

        $this->_page = $page;
        $data = $this->_storage->read() ?? [];
        $notes = $data['notes'] ?? [];

        if (!empty($notes)) {
            $total = count($notes);
            $totalPages = ceil($total / $this->_perPage);

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;

            $pageStart = ($page - 1) * $this->_perPage;
            $pageEnd = $pageStart + $this->_perPage;

            $i = 0;
            $count = 0;
            $response = "Available notes:\n";
            foreach ($notes as $title => $note) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $title . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} notes. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Type `get_note <title>` to retrieve a note by its title." . PHP_EOL;
            $response .= "Type `previous notes` or `next notes` to move through pages." . PHP_EOL;

            $this->_response = $response;
        } else {
            $this->_response = "No notes have been saved.";
        }
    }

    public function getNoteByName(array $args)
    {
        $title = $args[0] ?? '';

        if (!$title) {
            $this->_response = "Please provide a note title.";
            return;
        }

        $data = $this->_storage->read() ?? [];
        $notes = $data['notes'] ?? [];

        if (isset($notes[$title])) {
            $this->_response = "Title: {$title}\nContent:\n{$notes[$title]}";
        } else {
            $this->_response = "Note not found with the given title.";
        }
    }

    public function searchNotes(array $args)
    {
        $keywords = $args[0] ?? '';

        if (!$keywords) {
            $this->_response = "Please provide keywords to search.";
            return;
        }

        $data = $this->_storage->read() ?? [];
        $notes = $data['notes'] ?? [];

        $foundNotes = [];
        foreach ($notes as $title => $note) {
            if (strpos(strtolower($title), strtolower($keywords)) !== false ||
                strpos(strtolower($note), strtolower($keywords)) !== false) {
                $foundNotes[$title] = $note;
            }
        }

        if (!empty($foundNotes)) {
            $response = "Found notes:\n";
            foreach ($foundNotes as $title => $note) {
                $response .= "  - " . $title . PHP_EOL;
            }
            $response .= "Type `get_note <title>` to retrieve a note by its title." . PHP_EOL;
            $this->_response = $response;
        } else {
            $this->_response = "No notes found with the given keywords.";
        }
    }

    public function help()
    {
        $response = "Available commands:\n";
        $response .= "  - create note \"<title>\": Write a new note for regarding the given topic.\n";
        $response .= "  - save note \"<title>\" \"<content>\": Create a new note with the given title and content.\n";
        $response .= "  - list notes: List all available notes, with optional pagination.\n";
        $response .= "      Usage `list notes by <perPage> on <page>`: list notes by <perPage> on <page>\n";
        $response .= "  - get note \"<title>\": Retrieve a note by its title.\n";
        $response .= "  - search notes \"<keywords>\": Find notes by keywords in their title or content.\n";
        $response .= "  - next notes: Show the next page of the note list.\n";
        $response .= "  - previous notes: Show the previous page of the note list.\n";
        $response .= "  - help with notes: Show this help message.\n";

        $this->_response = $response;
    }

    private function getCurrentTask()
    {
        $storagePath = OPUS_ROOT . '/storage/system';
        
        $storage = new Disk([
            'location' => $storagePath,
            'name' => 'steps_data.json',
        ]);
        $storage->activate();

        // $goal = "Communicate with and assist the User.";
        $task = "Figure out User goals and objectives.";
        // $action = "Use command `update steps` go set a new goal.";

        $steps = $storage->read();
        if ($steps) {
            // $goal = $steps['goal']['description'];
            $task = "Assess best course of action";
            foreach ($steps['goal']['steps'] as $index => $process) {
                if (!$process['complete']) {
                    $task = $process['description'];
                    break;
                }
            }
            // $action = $steps['goal']['action']['description'];
        }

        return $task;
    }
}
