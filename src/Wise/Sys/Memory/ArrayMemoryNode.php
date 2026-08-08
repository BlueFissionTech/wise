<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Automata\Context;

class ArrayMemoryNode
{
    private Context $context;
    private array $edges;

    public function __construct(Context $context, array $edges = [])
    {
        $this->context = $context;
        $this->edges = $edges;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getEdges(): array
    {
        return $this->edges;
    }
}
