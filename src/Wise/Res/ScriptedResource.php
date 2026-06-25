<?php

namespace BlueFission\Wise\Res;

use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;

class ScriptedResource extends Obj
{
    private ScriptedResourceDefinition $definition;
    private ScriptedResourceRunner $runner;
    private string $response = '';

    public function __construct(ScriptedResourceDefinition $definition, ScriptedResourceRunner $runner)
    {
        parent::__construct();
        $this->definition = $definition;
        $this->runner = $runner;
    }

    public function response(): string
    {
        return $this->response;
    }

    public function handle(Behavior $behavior, $args): void
    {
        $action = $behavior->name();
        $this->response = $this->runner->run(
            $this->definition,
            $action,
            $this->normalizeArgs($args)
        );
    }

    private function normalizeArgs($args): array
    {
        if ($args instanceof Meta) {
            return is_array($args->data) ? $args->data : [];
        }

        if ($args === null) {
            return [];
        }

        return is_array($args) ? $args : [$args];
    }
}
