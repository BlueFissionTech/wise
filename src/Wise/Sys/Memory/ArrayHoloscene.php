<?php

namespace BlueFission\Wise\Sys\Memory;

class ArrayHoloscene
{
    private array $episodes = [];

    public function push(string $episodeId, array $statements): void
    {
        $this->episodes[$episodeId] = $statements;
    }

    public function contents(): array
    {
        return $this->episodes;
    }
}
