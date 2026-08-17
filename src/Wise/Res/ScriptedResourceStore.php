<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Val;

class ScriptedResourceStore extends Obj
{
    public function list(string $resource): array
    {
        $entries = store("_system.{$resource}.list");
        return Arr::is($entries) ? $entries : [];
    }

    public function save(string $resource, array $entries): void
    {
        store("_system.{$resource}.list", $entries);
    }

    public function get(string $resource, string $id): ?array
    {
        $entries = $this->list($resource);
        return Val::is($entries[$id] ?? null) ? $entries[$id] : null;
    }

    public function add(string $resource, array $entry): array
    {
        $entries = $this->list($resource);
        $id = Val::is($entry['id'] ?? null) ? $entry['id'] : Arr::size($entries);
        $entries[$id] = $entry;
        $this->save($resource, $entries);

        return $entry;
    }
}
