<?php

namespace BlueFission\Wise\Int;

use BlueFission\Obj;

class NullOrchestrator extends Obj implements IOrchestrator
{
    public function available(): bool
    {
        return false;
    }

    public function orchestrate(OrchestrationRequest $request): OrchestrationOutcome
    {
        return OrchestrationOutcome::unavailable();
    }
}
