<?php

namespace BlueFission\Wise\Exe;

interface IValidatingBridge
{
    public function validateFile(string $path, BridgeContext $context): BridgeResult;
}
