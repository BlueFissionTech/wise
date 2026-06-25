<?php

namespace BlueFission\Wise\Sys\IO;

use BlueFission\Str;
use BlueFission\Wise\Sys\FileSystemManager;

class CommandInputStream implements IInputSource
{
    private array $_lines;
    private int $_index = 0;
    private bool $_appendNewline;

    public function __construct(array $lines, bool $appendNewline = true)
    {
        $this->_lines = $lines;
        $this->_appendNewline = $appendNewline;
    }

    public static function fromFile(string $path, bool $appendNewline = true): self
    {
        $contents = FileSystemManager::readPath($path);
        $contents = Str::is($contents)
            ? rtrim($contents, "\r\n")
            : $contents;
        $lines = Str::is($contents)
            ? preg_split("/\\r?\\n/", $contents)
            : [];

        return new self($lines, $appendNewline);
    }

    public function read(): ?string
    {
        if (!$this->hasMore()) {
            return null;
        }

        $line = $this->_lines[$this->_index];
        $this->_index++;

        $line = is_string($line) ? rtrim($line, "\r\n") : '';

        return $this->_appendNewline ? $line . PHP_EOL : $line;
    }

    public function hasMore(): bool
    {
        return $this->_index < count($this->_lines);
    }
}
