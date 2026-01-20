<?php

namespace BlueFission\Wise\Sys\Drivers;

class BufferDisplayDriver implements IDisplayDriver
{
    private array $_content = [];
    private array $_sent = [];

    public function handle($data, $type = null, $style = null): void
    {
        $this->_content[] = $data;
    }

    public function send($data): void
    {
        $this->_sent[] = $data;
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
    }

    public function update(): void
    {
    }

    public function draw(): void
    {
    }

    public function print(): void
    {
        $this->_sent = array_merge($this->_sent, $this->_content);
        $this->_content = [];
    }

    public function clear(): void
    {
        $this->_content = [];
        $this->_sent = [];
    }

    public function clearScreen(): void
    {
        $this->clear();
    }

    public function output(): array
    {
        return array_merge($this->_sent, $this->_content);
    }
}
