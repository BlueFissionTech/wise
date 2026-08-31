<?php

namespace BlueFission\Wise\Res;

interface IProfileResourceStore
{
    public function all(ProfileScope $scope, string $resource): array;

    public function get(ProfileScope $scope, string $resource, string $id): ?array;

    public function put(ProfileScope $scope, string $resource, string $id, array $record): void;

    public function delete(ProfileScope $scope, string $resource, string $id): bool;
}
