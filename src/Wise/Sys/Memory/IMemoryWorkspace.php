<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Automata\Context;

interface IMemoryWorkspace
{
    public function record(string $text, string $episodeId): void;

    public function recordContext(Context $context, string $label, array $edges = []): void;

    public function memory(): object;

    public function holoscene(): object;

    public function setMaxSize(?int $maxSize): void;

    public function maxSize(): ?int;

    public function setDecayRate(float $seconds): void;
}
