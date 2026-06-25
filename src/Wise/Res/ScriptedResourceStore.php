<?php

namespace BlueFission\Wise\Res;

use BlueFission\Obj;

class ScriptedResourceStore extends Obj
{
    public function list(string $resource): array
    {
        $entries = store("_system.{$resource}.list");
        return is_array($entries) ? $entries : [];
    }

    public function save(string $resource, array $entries): void
    {
        store("_system.{$resource}.list", $entries);
    }

    public function get(string $resource, string $id): ?array
    {
        $entries = $this->list($resource);
        return $entries[$id] ?? null;
    }

    public function add(string $resource, array $entry): array
    {
        $entries = $this->list($resource);
        $id = $entry['id'] ?? count($entries);
        $entries[$id] = $entry;
        $this->save($resource, $entries);

        return $entry;
    }
}
