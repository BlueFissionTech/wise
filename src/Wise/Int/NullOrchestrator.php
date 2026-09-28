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
        $envelope = new OrchestrationEnvelope($request);

        return new OrchestrationOutcome($envelope->outcome([
            'status' => 'unavailable',
            'metadata' => ['reason' => 'orchestration_unavailable'],
        ], [
            'status' => 'unsupported',
            'code' => 'provider_unavailable',
        ]));
    }
}
