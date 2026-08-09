<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Date;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Exe\BridgeRegistry;

class ScriptedResourceRunner
{
    private Kernel $kernel;
    private BridgeRegistry $bridges;
    private ScriptedResourceStore $store;

    public function __construct(Kernel $kernel, BridgeRegistry $bridges, ?ScriptedResourceStore $store = null)
    {
        $this->kernel = $kernel;
        $this->bridges = $bridges;
        $this->store = $store ?? new ScriptedResourceStore();
    }

    public function run(ScriptedResourceDefinition $definition, string $action, array $args): string
    {
        $path = $definition->path();
        if (!Val::is($this->bridges->bridgeForFile($path))) {
            return 'No script bridge registered for ' . $definition->name() . '.';
        }

        $resource = $definition->name();
        $normalizedArgs = $this->normalizeArgs($args, $resource);
        $entries = $this->store->list($resource);
        $entry = null;
        $recipient = null;
        $content = null;
        $detail = null;

        if ($action === 'send') {
            [$recipient, $content] = $this->parseRecipientAndContent($normalizedArgs);
            $entry = [
                'id' => Str::uuid4(),
                'recipient' => $recipient,
                'content' => $content,
                'status' => 'queued',
                'created_at' => (string)Date::now()->format('c'),
            ];
            $this->store->add($resource, $entry);
            $entries = $this->store->list($resource);
        } elseif ($action === 'show') {
            $id = (string)(Val::is($normalizedArgs[0] ?? null) ? $normalizedArgs[0] : '');
            if ($id !== '') {
                $entry = $entries[$id] ?? null;
            }
            if (!$entry) {
                $detail = "Message '{$id}' not found.";
            }
        }

        $formattedEntries = $this->formatEntries($entries);
        if ($action === 'list' && $formattedEntries === []) {
            $formattedEntries = ['(none)'];
        }
        if ($action === 'show' && $entry) {
            $detail = $this->formatEntry($entry);
        }

        $vars = [
            'resource' => $resource,
            'action' => $action,
            'args' => $normalizedArgs,
            'entries' => $formattedEntries,
            'count' => Arr::size($formattedEntries),
            'total' => Arr::size($entries),
            'entry' => $entry,
            'detail' => $detail,
            'recipient' => $recipient,
            'content' => $content,
        ];

        $messages = [];
        $context = $this->kernel->buildBridgeContext($messages, $vars);
        $result = $this->bridges->runFile($path, $context);
        $output = $result->output();

        if (Str::isEmpty($output) && Arr::isNotEmpty($messages)) {
            $output = Arr::make($messages)->join(PHP_EOL)->val();
        }

        return $output;
    }

    private function normalizeArgs(array $args, string $resource): array
    {
        $filtered = [];
        $resourcePlural = Str::pluralize($resource);
        foreach ($args as $arg) {
            $value = Str::make((string)$arg)->trim();
            if ($value->isEmpty()) {
                continue;
            }
            $lower = $value->copy()->lower()->val();
            if (Arr::has(['new', $resource, $resourcePlural], $lower, true)) {
                continue;
            }
            $filtered[] = $value->val();
        }

        return $filtered;
    }

    private function parseRecipientAndContent(array $args): array
    {
        if (Arr::isEmpty($args)) {
            return ['user', ''];
        }

        if (Arr::size($args) === 1) {
            return ['user', (string)$args[0]];
        }

        $recipient = (string)$args[0];
        $content = (string)Arr::make($args)->pop();

        return [$recipient !== '' ? $recipient : 'user', $content];
    }

    private function formatEntries(array $entries): array
    {
        $lines = [];
        foreach ($entries as $entry) {
            if (!Arr::is($entry)) {
                $lines[] = (string)$entry;
                continue;
            }
            $lines[] = $this->formatEntry($entry);
        }

        return $lines;
    }

    private function formatEntry(array $entry): string
    {
        $id = Val::is($entry['id'] ?? null) ? $entry['id'] : '';
        $recipient = Val::is($entry['recipient'] ?? null) ? $entry['recipient'] : 'user';
        $content = Val::is($entry['content'] ?? null) ? $entry['content'] : '';
        $preview = $this->preview($content);

        return "{$id} (to {$recipient}): {$preview}";
    }

    private function preview(string $content): string
    {
        $content = Str::make($content)->trim();
        if ($content->isEmpty()) {
            return '(empty)';
        }

        $max = 64;
        if ($content->len() <= $max) {
            return $content->val();
        }

        return $content->sub(0, $max - 3)->val() . '...';
    }
}
