<?php

namespace BlueFission\Wise\Exe;

interface IExecutingBridge
{
    public function execute(ExecutionRequest $request, BridgeContext $context): BridgeResult;
}
