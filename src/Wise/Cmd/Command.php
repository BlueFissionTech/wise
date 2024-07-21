<?php
namespace BlueFission\Wise\Cmd;

class Command
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