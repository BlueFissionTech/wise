<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Memory\MemoryRecall;
use BlueFission\Str;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Wise\Usr\Profile;

class SynthetiqMemoryAdapter implements MemoryAdapterInterface
{
    protected WorkingMemoryCoordinator $memory;
    protected Profile $systemActor;
    protected float $similarityThreshold = 0.4;
    protected int $maxRelated = 5;
    protected float $biasWeight = 1.0;
    protected string $defaultScope = 'global';
    protected $actorResolver;

    public function __construct(
        WorkingMemoryCoordinator $memory,
        ?Profile $systemActor = null,
        array $options = [],
        ?callable $actorResolver = null
    ) {
        $this->memory = $memory;
        $this->systemActor = $systemActor ?? new Profile('system', ['system']);
        $this->actorResolver = $actorResolver;

        if (isset($options['similarity_threshold'])) {
            $this->similarityThreshold = (float)$options['similarity_threshold'];
        }
        if (isset($options['max_related'])) {
            $this->maxRelated = (int)$options['max_related'];
        }
        if (isset($options['bias_weight'])) {
            $this->biasWeight = (float)$options['bias_weight'];
        }
        if (isset($options['default_scope'])) {
            $this->defaultScope = (string)$options['default_scope'];
        }
    }

    public function recordExchange(string $input, string $response, Context $context, array $meta = []): void
    {
        $scope = $this->resolveScope($context, $meta);
        $ownerId = $this->resolveOwnerId($context, $meta);
        $actor = $this->resolveActor($context, $meta, $scope, $ownerId);

        $text = Str::trim($input . ' ' . $response);
        if ($text === '') {
            return;
        }

        $episodeId = $meta['episode_id'] ?? uniqid('exchange_', true);
        $stored = $this->memory->record($scope, $text, $episodeId, $actor, $ownerId);
        if (!$stored) {
            return;
        }

        $partition = $this->memory->memory($scope, $actor, $ownerId);
        if ($partition) {
            $exchangeContext = $this->buildExchangeContext($input, $response, $context, $scope, $meta);
            $label = $meta['memory_label'] ?? ('synthetiq:' . $episodeId);
            $partition->recordContext($exchangeContext, $label, $meta['edges'] ?? []);
        }

        Dev::do('wise.memory.synthetiq.recorded', [
            'scope' => $scope,
            'owner_id' => $ownerId,
            'episode_id' => $episodeId,
        ]);
    }

    public function recall(string $input, Context $context, array $meta = []): MemoryRecall
    {
        $scope = $this->resolveScope($context, $meta);
        $ownerId = $this->resolveOwnerId($context, $meta);
        $actor = $this->resolveActor($context, $meta, $scope, $ownerId);

        $partition = $this->memory->memory($scope, $actor, $ownerId);
        if (!$partition) {
            return new MemoryRecall();
        }

        $memory = $partition->memory();
        if (!method_exists($memory, 'recallSimilar')) {
            return new MemoryRecall();
        }

        $query = $this->buildQueryContext($input, $context, $scope, $meta);
        $results = $memory->recallSimilar($query, $this->similarityThreshold);
        $results = $this->limitResults($results);
        $intentBiases = $this->buildIntentBiases($results);

        $recall = new MemoryRecall($results, $intentBiases, [
            'scope' => $scope,
            'owner_id' => $ownerId,
        ]);

        Dev::do('wise.memory.synthetiq.recalled', [
            'scope' => $scope,
            'owner_id' => $ownerId,
            'intent_biases' => $intentBiases,
        ]);

        return $recall;
    }

    protected function resolveScope(Context $context, array $meta): string
    {
        $scope = $meta['scope'] ?? $context->get('memory_scope', $this->defaultScope);
        return (string)$scope;
    }

    protected function resolveOwnerId(Context $context, array $meta): ?string
    {
        $ownerId = $meta['owner_id'] ?? $meta['user_id'] ?? $context->get('user_id');
        if ($ownerId === null || $ownerId === '') {
            return null;
        }

        return (string)$ownerId;
    }

    protected function resolveActor(Context $context, array $meta, string $scope, ?string $ownerId): Profile
    {
        if ($this->actorResolver) {
            $resolved = call_user_func($this->actorResolver, $context, $meta, $scope, $ownerId);
            if ($resolved instanceof Profile) {
                return $resolved;
            }
        }

        $actor = $meta['actor'] ?? $context->get('actor');
        if ($actor instanceof Profile) {
            return $actor;
        }

        if ($scope === 'global') {
            return $this->systemActor;
        }

        if ($ownerId !== null && $ownerId !== '') {
            return new Profile($ownerId);
        }

        return $this->systemActor;
    }

    protected function buildQueryContext(string $input, Context $context, string $scope, array $meta): Context
    {
        $query = new Context();
        $query->set('input', $input);
        $query->set('scope', $scope);

        $ownerId = $this->resolveOwnerId($context, $meta);
        if ($ownerId !== null) {
            $query->set('user_id', $ownerId);
        }

        return $query;
    }

    protected function buildExchangeContext(
        string $input,
        string $response,
        Context $context,
        string $scope,
        array $meta
    ): Context {
        $memoryContext = new Context();
        $memoryContext->set('input', $input);
        $memoryContext->set('response', $response);
        $memoryContext->set('scope', $scope);
        $memoryContext->set('timestamp', $meta['timestamp'] ?? time());

        $intent = $context->get('current_intent');
        if ($intent instanceof Intent) {
            $memoryContext->set('intent_label', $intent->getLabel());
        } elseif (is_string($intent)) {
            $memoryContext->set('intent_label', $intent);
        }

        $confidence = $context->get('intent_confidence');
        if ($confidence !== null) {
            $memoryContext->set('intent_confidence', $confidence);
        }

        if (isset($meta['user_id'])) {
            $memoryContext->set('user_id', $meta['user_id']);
        }
        if (isset($meta['session_id'])) {
            $memoryContext->set('session_id', $meta['session_id']);
        }

        return $memoryContext;
    }

    protected function limitResults(array $results): array
    {
        if ($this->maxRelated <= 0 || count($results) <= $this->maxRelated) {
            return $results;
        }

        return array_slice($results, 0, $this->maxRelated, true);
    }

    protected function buildIntentBiases(array $results): array
    {
        $intentBiases = [];

        foreach ($results as $label => $entry) {
            $entryContext = $entry['context'] ?? null;
            if (!$entryContext instanceof Context) {
                continue;
            }

            $intentLabel = $entryContext->get('intent_label');
            if (!$intentLabel) {
                continue;
            }

            $weight = (float)($entry['similarity'] ?? 1.0) * $this->biasWeight;
            $intentBiases[$intentLabel] = ($intentBiases[$intentLabel] ?? 0.0) + $weight;
        }

        if (!empty($intentBiases)) {
            arsort($intentBiases);
        }

        return $intentBiases;
    }
}
