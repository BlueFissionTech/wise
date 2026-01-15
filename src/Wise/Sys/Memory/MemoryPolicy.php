<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Wise\Usr\Profile;

interface MemoryPolicy
{
    public function canRead(Profile $actor, string $scope, ?string $ownerId = null): bool;

    public function canWrite(Profile $actor, string $scope, ?string $ownerId = null): bool;
}
