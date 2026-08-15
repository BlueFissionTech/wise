<?php

namespace BlueFission\Wise\Cmd;

interface ICommandProcessor
{
    public function process(CommandRequest|Command|array|string $request): CommandResult;
}
