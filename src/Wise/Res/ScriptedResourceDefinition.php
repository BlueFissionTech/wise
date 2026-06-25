<?php

namespace BlueFission\Wise\Res;

use BlueFission\Obj;

class ScriptedResourceDefinition extends Obj
{
    private string $name;
    private string $path;
    private array $actions;
    private string $description;
    private string $hint;

    public function __construct(string $name, string $path, array $actions, string $description = '', string $hint = '')
    {
        parent::__construct();
        $this->name = $name;
        $this->path = $path;
        $this->actions = $actions;
        $this->description = $description;
        $this->hint = $hint;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function hint(): string
    {
        return $this->hint;
    }
}
