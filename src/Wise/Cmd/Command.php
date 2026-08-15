<?php
namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Str;

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

    public function complete(): bool
    {
        return Str::isNotEmpty((string)$this->verb) && Arr::isNotEmpty($this->resources);
    }

    public function description(): string
    {
        $parts = Arr::make([(string)$this->verb])
            ->mergeRecursive(Arr::is($this->resources) ? $this->resources : [])
            ->mergeRecursive(Arr::is($this->args) ? $this->args : [])
            ->filter(static fn ($value): bool => Str::isNotEmpty((string)$value));

        return $parts->join(' ')->val();
    }

    public function toArray(): array
    {
        return [
            'verb' => $this->verb,
            'resources' => Arr::is($this->resources) ? $this->resources : [],
            'args' => Arr::is($this->args) ? $this->args : [],
        ];
    }
}
