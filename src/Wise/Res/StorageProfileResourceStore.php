<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Data\Storage\Storage;
use BlueFission\Obj;
use BlueFission\Val;

final class StorageProfileResourceStore extends Obj implements IProfileResourceStore
{
    public function __construct(private Storage $storage)
    {
        parent::__construct();
        $this->storage->activate();
        $this->storage->read();
    }

    public function all(ProfileScope $scope, string $resource): array
    {
        $resources = $this->resources();

        return Arr::is($resources[$scope->key()][$resource] ?? null)
            ? $resources[$scope->key()][$resource]
            : [];
    }

    public function get(ProfileScope $scope, string $resource, string $id): ?array
    {
        $records = $this->all($scope, $resource);

        return Arr::is($records[$id] ?? null) ? $records[$id] : null;
    }

    public function put(ProfileScope $scope, string $resource, string $id, array $record): void
    {
        $resources = $this->resources();
        $resources[$scope->key()][$resource][$id] = $record;
        $this->storage->profileResources = $resources;
        $this->storage->write();
    }

    public function delete(ProfileScope $scope, string $resource, string $id): bool
    {
        $resources = $this->resources();
        if (!Val::is($resources[$scope->key()][$resource][$id] ?? null)) {
            return false;
        }

        unset($resources[$scope->key()][$resource][$id]);
        $this->storage->profileResources = $resources;
        $this->storage->write();

        return true;
    }

    private function resources(): array
    {
        return Arr::is($this->storage->profileResources ?? null)
            ? $this->storage->profileResources
            : [];
    }
}
