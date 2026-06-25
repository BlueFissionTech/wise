<?php

namespace BlueFission\Wise\Exe;

interface IBridge
{
    public function name(): string;

    /**
     * @return array<int, string>
     */
    public function extensions(): array;

    public function canHandleFile(string $path): bool;

    public function runFile(string $path, BridgeContext $context): BridgeResult;

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult;
}
