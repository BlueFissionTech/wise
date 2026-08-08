<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Str;

class ArrayMemoryGraph
{
    private array $nodes = [];

    public function addMemory(string $label, Context $context, array $edges = []): void
    {
        $this->nodes[$label] = new ArrayMemoryNode($context, $edges);
    }

    public function contents(): array
    {
        return $this->nodes;
    }

    public function forget(string $label): void
    {
        unset($this->nodes[$label]);
    }

    public function recallSimilar(Context $query, float $threshold = 0.0): array
    {
        $input = Str::make((string)$query->get('input', ''))->lower()->trim()->val();
        $results = [];

        foreach ($this->nodes as $label => $node) {
            $context = $node->getContext();
            $candidate = self::contextText($context);
            $score = self::similarity($input, $candidate);

            if ($score < $threshold) {
                continue;
            }

            $results[$label] = [
                'context' => $context,
                'similarity' => $score,
                'edges' => $node->getEdges(),
            ];
        }

        uasort($results, static fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $results;
    }

    private static function contextText(Context $context): string
    {
        $values = [
            $context->get('input', ''),
            $context->get('response', ''),
            $context->get('value', ''),
            $context->get('label', ''),
        ];

        return Arr::make($values)
            ->filter(fn($value) => !Str::isEmpty((string)$value))
            ->map(fn($value) => Str::make((string)$value)->lower()->trim()->val())
            ->join(' ')
            ->val();
    }

    private static function similarity(string $input, string $candidate): float
    {
        if (Str::isEmpty($input) || Str::isEmpty($candidate)) {
            return 0.0;
        }

        if (Str::has($candidate, $input) || Str::has($input, $candidate)) {
            return 1.0;
        }

        similar_text($input, $candidate, $percent);

        return $percent / 100;
    }
}
