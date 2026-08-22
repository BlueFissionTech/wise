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

    public function discover(): array;
}
