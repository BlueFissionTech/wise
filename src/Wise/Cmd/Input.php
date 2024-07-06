<?php
namespace BlueFission\Wise\Cmd;

// Command.php
class Input
{
    public $verb;
    public $resources;
    public $args;

    public function __construct()
    {
        $this->resources = [];
        $this->args = [];
    }
}