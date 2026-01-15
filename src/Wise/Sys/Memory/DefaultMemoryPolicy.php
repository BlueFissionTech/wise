<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Wise\Usr\Profile;

class DefaultMemoryPolicy implements MemoryPolicy
{
    public function canRead(Profile $actor, string $scope, ?string $ownerId = null): bool
    {
        if ($this->isPrivileged($actor)) {
            return true;
        }

        if ($scope === 'global') {
            return false;
        }

        return $ownerId !== null && $actor->id() === $ownerId;
    }

    public function canWrite(Profile $actor, string $scope, ?string $ownerId = null): bool
    {
        if ($this->isPrivileged($actor)) {
            return true;
        }

        if ($scope === 'global') {
            return false;
        }

        return $ownerId !== null && $actor->id() === $ownerId;
    }

    protected function isPrivileged(Profile $actor): bool
    {
        return $actor->hasRole('admin') || $actor->hasRole('system');
    }
}
