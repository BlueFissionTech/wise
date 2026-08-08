<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

class ArrayMemoryWorkspace implements IMemoryWorkspace
{
    protected object $_reader;
    protected ArrayMemoryGraph $_memory;
    protected ArrayHoloscene $_holoscene;
    protected ?int $_maxSize = null;
    protected float $_decayRate = 3600.0;

    public function __construct(object $reader, ?ArrayMemoryGraph $memory = null, ?ArrayHoloscene $holoscene = null)
    {
        if (!method_exists($reader, 'readDocument') || !method_exists($reader, 'toHoloscene')) {
            throw new \InvalidArgumentException('Reader must implement readDocument() and toHoloscene().');
        }

        $this->_reader = $reader;
        $this->_memory = $memory ?? new ArrayMemoryGraph();
        $this->_holoscene = $holoscene ?? new ArrayHoloscene();
    }

    public function record(string $text, string $episodeId): void
    {
        $statements = $this->_reader->readDocument($text);
        try {
            $this->_reader->toHoloscene($statements, $this->_holoscene, $this->_memory, $episodeId);
        } catch (\Throwable $e) {
            $this->recordFallbackStatements($statements, $episodeId);
        }
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

    public function memory(): object
    {
        return $this->_memory;
    }

    public function holoscene(): object
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
        if ($seconds > 0) {
            $this->_decayRate = $seconds;
        }
    }

    protected function applyRetention(): void
    {
        if (Val::isNull($this->_maxSize) || $this->_maxSize <= 0) {
            return;
        }

        $nodes = $this->_memory->contents();
        if (Arr::count($nodes) <= $this->_maxSize) {
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
            $connections = Arr::count($node->getEdges());
            $ageSeconds = Num::make($now - $lastSeen)->max(0);
            $agePenalty = $ageSeconds / $this->_decayRate;
            $scored[$label] = $reinforcement + $connections - $agePenalty;
        }

        arsort($scored);
        $keep = Arr::make($scored)->keys()->slice(0, $this->_maxSize)->val();

        foreach ($nodes as $label => $node) {
            if (!Arr::has($keep, $label, true)) {
                $this->_memory->forget($label);
            }
        }
    }

    protected function recordFallbackStatements(array $statements, string $episodeId): void
    {
        foreach ($statements as $index => $statement) {
            $context = new Context();
            $context->set('input', (string)$statement);
            $context->set('episode_id', $episodeId);
            $this->_memory->addMemory($episodeId . ':' . Str::sub(sha1((string)$statement . '|' . $index), 0, 16), $context);
        }

        if (method_exists($this->_holoscene, 'push')) {
            $this->_holoscene->push($episodeId, $statements);
        }
    }
}
