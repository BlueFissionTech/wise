<?php

namespace BlueFission\Wise\Cmd;

interface ICommandRuntime
{
    public function execute(
        CommandRequest|Command|array|string $request,
        RuntimeContext $context
    ): RuntimeResult;

    public function executeScript(
        string $path,
        RuntimeContext $context,
        string $standardInput = ''
    ): RuntimeResult;

    public function discover(?RuntimeContext $context = null): array;

    public function descriptors(?RuntimeContext $context = null): array;

    public function descriptor(string $identifier, ?RuntimeContext $context = null): CommandDescriptor;
}
