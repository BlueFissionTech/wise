<?php

namespace BlueFission\Wise\Exe;

class BridgeRegistry
{
    /** @var array<int, IBridge> */
    private array $bridges = [];

    public function register(IBridge $bridge): void
    {
        $this->bridges[] = $bridge;
    }

    public function bridgeForFile(string $path): ?IBridge
    {
        foreach ($this->bridges as $bridge) {
            if ($bridge->canHandleFile($path)) {
                return $bridge;
            }
        }

        return null;
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $bridge = $this->bridgeForFile($path);
        if (!$bridge) {
            return BridgeResult::failure('No bridge registered for file: ' . $path);
        }

        return $bridge->runFile($path, $context);
    }
}
