<?php

namespace BlueFission\Wise\Nav;

class SynthetiqProxy implements INavigator
{
    protected object $_synthetiq;

    public function __construct(object $synthetiq)
    {
        if (!method_exists($synthetiq, 'processInput')) {
            throw new \InvalidArgumentException('Synthetiq engine must implement processInput().');
        }

        $this->_synthetiq = $synthetiq;
    }

    public function process(string $input): string
    {
        return (string)$this->_synthetiq->processInput($input);
    }

    public function addRoute(string $statement, string $type, array|string $to = []): void
    {
        if (!method_exists($this->_synthetiq, 'addRoute')) {
            throw new \RuntimeException('Synthetiq does not support addRoute.');
        }

        $this->_synthetiq->addRoute($statement, $type, $to);
    }

    public function addIntentKeywords(string $type, array $keywords, ?int $priorityBase = null): void
    {
        if (!method_exists($this->_synthetiq, 'addIntentKeywords')) {
            throw new \RuntimeException('Synthetiq does not support addIntentKeywords.');
        }

        $this->_synthetiq->addIntentKeywords($type, $keywords, $priorityBase);
    }
}
