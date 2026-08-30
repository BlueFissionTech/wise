<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Data\Storage\Storage;
use BlueFission\Obj;

final class StorageContinuationStore extends Obj implements IContinuationStore
{
    public function __construct(private Storage $storage, private int $replayLimit = 50)
    {
        parent::__construct();
        $this->storage->activate();
        $this->storage->read();
    }

    public function put(ContinuationState $state): void
    {
        $pending = Arr::is($this->storage->continuationStates ?? null)
            ? $this->storage->continuationStates
            : [];
        $pending[$state->token()] = $state->toArray();
        $this->storage->continuationStates = $pending;
        $this->storage->write();
    }

    public function consume(string $token, array $scope = []): ContinuationClaim
    {
        $consumed = Arr::is($this->storage->consumedContinuations ?? null)
            ? $this->storage->consumedContinuations
            : [];
        if (Arr::has($consumed, $token, true)) {
            return new ContinuationClaim(ContinuationClaim::REPLAYED);
        }

        $pending = Arr::is($this->storage->continuationStates ?? null)
            ? $this->storage->continuationStates
            : [];
        if (!Arr::hasKey($pending, $token) || !Arr::is($pending[$token])) {
            return new ContinuationClaim(ContinuationClaim::MISSING);
        }

        $state = ContinuationState::fromArray($pending[$token]);
        if ($state->expired()) {
            unset($pending[$token]);
            $this->storage->continuationStates = $pending;
            $this->storage->write();
            return new ContinuationClaim(ContinuationClaim::EXPIRED);
        }
        if (!$state->matches($scope)) {
            return new ContinuationClaim(ContinuationClaim::MISMATCHED);
        }

        unset($pending[$token]);
        $consumed = Arr::make($consumed)->push($token)->slice(-$this->replayLimit)->toArray();
        $this->storage->continuationStates = $pending;
        $this->storage->consumedContinuations = $consumed;
        $this->storage->write();

        return new ContinuationClaim(ContinuationClaim::ACCEPTED, $state);
    }
}
