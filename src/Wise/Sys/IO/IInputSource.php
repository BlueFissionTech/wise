<?php

namespace BlueFission\Wise\Sys\IO;

interface IInputSource
{
    public function read(): ?string;

    public function hasMore(): bool;
}
