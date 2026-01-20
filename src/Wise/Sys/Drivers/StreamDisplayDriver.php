<?php

namespace BlueFission\Wise\Sys\Drivers;

use BlueFission\Val;

class StreamDisplayDriver implements IDisplayDriver
{
    private string $_path;
    private bool $_append;
    private $handle = null;
    private array $_content = [];

    public function __construct(string $path, bool $append = false)
    {
        $this->_path = $path;
        $this->_append = $append;
    }

    public function handle($data, $type = null, $style = null): void
    {
        $this->_content[] = $data;
    }

    public function send($data): void
    {
        $this->write($data);
    }

    public function getContent(): array
    {
        return $this->_content;
    }

    public function flush(): void
    {
        $this->_content = [];
    }

    public function getTerminalSize(): array
    {
        return [80, 24];
    }

    public function init(): void
    {
        $this->open();
    }

    public function update(): void
    {
    }

    public function draw(): void
    {
    }

    public function print(): void
    {
        foreach ($this->_content as $line) {
            $this->write($line);
        }
        $this->_content = [];
    }

    public function clear(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
        $this->_content = [];
    }

    public function clearScreen(): void
    {
        $this->clear();
    }

    private function open(): void
    {
        if ($this->handle) {
            return;
        }

        $mode = $this->_append ? 'ab' : 'wb';
        $this->handle = fopen($this->_path, $mode);
    }

    private function write($data): void
    {
        if (Val::isNull($data)) {
            return;
        }

        if (!$this->handle) {
            $this->open();
        }

        if ($this->handle) {
            fwrite($this->handle, (string)$data);
        }
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }
}
