<?php
namespace BlueFission\Wise\Commands;

use BlueFission\Services\Service;
use BlueFission\Data\FileSystem;

class FileCommand extends Service {
    private $_fileSystem;

    public function __construct() {
        parent::__construct();
        $this->_fileSystem = new FileSystem([
            'root'=>OPUS_ROOT . 'storage/files',
            'mode'=>'a+',
            'filter'=>['..', 'txt', 'html', 'json', 'xml', 'csv', 'tsv', 'md', '' ]
        ]);
        $this->createFilesDirectory();
    }

    public function handle($behavior, $args) {
        $action = $behavior->name();

        $this->_response = "Invalid action specified.";

        switch ($action) {
            case 'make':
                $this->createFile($args[0]);
                break;
            case 'edit':
                if (count($args) == 1) {
                    $parts = explode(' ', $args[0], 2);
                } elseif (count($args) > 1) {
                    $parts = $args;
                } else {
                    $parts = [];
                }
                $this->editFile($parts[0], $parts[1]);
                break;
            case 'open':
                $this->openFile($args[0]);
                break;
            case 'save':
                $this->saveFile($args[0]);
                break;
            case 'delete':
                $this->deleteFile($args[0]);
                break;
            case 'list':
                $this->listDirectory();
                break;
            case 'add':
                if (count($args) == 1) {
                    $parts = explode(' ', $args[0], 2);
                } elseif (count($args) > 1) {
                    $parts = $args;
                } else {
                    $parts = [];
                }
                if (count($parts) > 1) {
                    $this->addContentToFile($parts[0], $parts[1]);
                } else {
                    $this->_response = "Error! Missing content to add to file.";
                }
                break;
            default:
                case 'help':
                $this->help();
                // throw new \InvalidArgumentException("Error! Invalid action specified.");
        }
    }

    private function createFilesDirectory() {
        $path = OPUS_ROOT . '/storage/files';
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function help() {
        $response = "Available commands:" . PHP_EOL;
        $response .= "  create file <filename>    - Creates a new file with the specified filename" . PHP_EOL;
        $response .= "  edit file <filename> <content> - Edits the specified file with new content" . PHP_EOL;
        $response .= "  add file <filename> <content> - Edits the specified file with new content appended to the end" . PHP_EOL;
        $response .= "  open file <filename>      - Opens and reads the content of the specified file" . PHP_EOL;
        $response .= "  save file <filename>      - Saves the specified file" . PHP_EOL;
        $response .= "  list                 - Lists all files" . PHP_EOL;

        $this->_response = $response;
    }

    private function createFile($filename) {
        // trim whitespace and remove quotes from filename
        $filename = trim(trim($filename, '"'));

        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->write();
        $this->_fileSystem->close();
        $this->_response = $this->_fileSystem->status();

    }

    private function deleteFile($filename) {
        // trim whitespace and remove quotes from filename
        $filename = trim(trim($filename, '"'));

        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->close();
        $this->_fileSystem->delete(true);
        $this->_response = $this->_fileSystem->status();

    }

    private function editFile($filename, $content) {
        $filename = trim(trim($filename, '"'));
        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->read();
        $this->_fileSystem->contents($content);
        $this->_fileSystem->write();
        $this->_fileSystem->close();

        $this->_response = $this->_fileSystem->status();
    }

    private function openFile($filename) {
        $filename = trim(trim($filename, '"'));
        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->read();
        $contents = $this->_fileSystem->contents();
        $this->_fileSystem->close();

        $this->_response = $contents ?? $this->_fileSystem->status();
    }

    private function saveFile($filename) {
        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->write();
        $this->_fileSystem->close();
        
        $this->_response = $this->_fileSystem->status();
    }

    private function addContentToFile($filename, $content) {
        $filename = trim(trim($filename, '"'));
        $path = $filename;
        $this->_fileSystem->open($path);
        $this->_fileSystem->read();
        $existingContent = $this->_fileSystem->contents();
        $updatedContent = $existingContent . $content;
        $this->_fileSystem->contents($updatedContent);
        $this->_fileSystem->write();
        $this->_fileSystem->close();

        $this->_response = $this->_fileSystem->status();
    }

    private function listDirectory() {
        $path = OPUS_ROOT . '/storage/files';
        $files = scandir($path);

        $response = "List of files in the directory:" . PHP_EOL;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $response .= "  - " . $file . PHP_EOL;
        }

        $this->_response = $response;
    }
}
