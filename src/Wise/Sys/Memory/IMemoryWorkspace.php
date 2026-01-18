<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\Abs2Memory;

interface IMemoryWorkspace
{
    public function record(string $text, string $episodeId): void;

    public function recordContext(Context $context, string $label, array $edges = []): void;

    public function memory(): Abs2Memory;

    public function holoscene(): Holoscene;

    public function setMaxSize(?int $maxSize): void;

    public function maxSize(): ?int;

    public function setDecayRate(float $seconds): void;
}
