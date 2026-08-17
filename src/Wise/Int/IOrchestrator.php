<?php

namespace BlueFission\Wise\Int;

interface IOrchestrator
{
    public function available(): bool;

    public function orchestrate(OrchestrationRequest $request): OrchestrationOutcome;
}
