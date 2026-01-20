<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\Abs2Memory;

class MemoryPartition implements IMemoryWorkspace
{
    protected object $_reader;
    protected Abs2Memory $_memory;
    protected Holoscene $_holoscene;
    protected ?int $_maxSize = null;
    protected float $_decayRate = 3600.0;

    public function __construct(object $reader, ?Abs2Memory $memory = null, ?Holoscene $holoscene = null)
    {
        if (!method_exists($reader, 'readDocument') || !method_exists($reader, 'toHoloscene')) {
            throw new \InvalidArgumentException('Reader must implement readDocument() and toHoloscene().');
        }

        $this->_reader = $reader;
        $this->_memory = $memory ?? new Abs2Memory();
        $this->_holoscene = $holoscene ?? new Holoscene();
    }

    public function record(string $text, string $episodeId): void
    {
        $statements = $this->_reader->readDocument($text);
        $this->_reader->toHoloscene($statements, $this->_holoscene, $this->_memory, $episodeId);
        $this->applyRetention();
    }

    public function recordContext(Context $context, string $label, array $edges = []): void
    {
        $now = time();
        $timestamp = (int)$context->get('timestamp', 0);
        if ($timestamp <= 0) {
            $timestamp = $now;
            $context->set('timestamp', $timestamp);
        }
        $lastSeen = (int)$context->get('last_seen', 0);
        if ($lastSeen <= 0) {
            $context->set('last_seen', $timestamp);
        }

        $this->_memory->addMemory($label, $context, $edges);
        $this->applyRetention();
    }

    public function memory(): Abs2Memory
    {
        return $this->_memory;
    }

    public function holoscene(): Holoscene
    {
        return $this->_holoscene;
    }

    public function setMaxSize(?int $maxSize): void
    {
        $this->_maxSize = $maxSize;
    }

    public function maxSize(): ?int
    {
        return $this->_maxSize;
    }

    public function setDecayRate(float $seconds): void
    {
        $this->_decayRate = $seconds > 0 ? $seconds : $this->_decayRate;
    }

    protected function applyRetention(): void
    {
        if ($this->_maxSize === null || $this->_maxSize <= 0) {
            return;
        }

        $nodes = $this->_memory->contents();
        $count = count($nodes);
        if ($count <= $this->_maxSize) {
            return;
        }

        $now = time();
        $scored = [];
        foreach ($nodes as $label => $node) {
            $context = $node->getContext();
            $lastSeen = (int)$context->get('last_seen', 0);
            if ($lastSeen <= 0) {
                $timestamp = (int)$context->get('timestamp', 0);
                $lastSeen = $timestamp > 0 ? $timestamp : $now;
                $context->set('last_seen', $lastSeen);
                $node->setContext($context);
            }

            $reinforcement = (float)($context->get('reinforcement') ?? 0);
            $connections = count($node->getEdges());
            $ageSeconds = max(0, $now - $lastSeen);
            $agePenalty = $ageSeconds / $this->_decayRate;
            $score = $reinforcement + $connections - $agePenalty;

            $scored[$label] = $score;
        }

        arsort($scored);
        $keep = array_slice(array_keys($scored), 0, $this->_maxSize);

        foreach ($nodes as $label => $node) {
            if (!in_array($label, $keep, true)) {
                $this->_memory->forget($label);
            }
        }
    }
}
