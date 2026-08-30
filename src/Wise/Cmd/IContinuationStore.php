<?php

namespace BlueFission\Wise\Cmd;

interface IContinuationStore
{
    public function put(ContinuationState $state): void;

    public function consume(string $token, array $scope = []): ContinuationClaim;
}
