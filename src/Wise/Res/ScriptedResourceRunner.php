<?php

namespace BlueFission\Wise\Res;

use BlueFission\Str;
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
        if (!$this->bridges->bridgeForFile($path)) {
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
                'created_at' => date('c'),
            ];
            $this->store->add($resource, $entry);
            $entries = $this->store->list($resource);
        } elseif ($action === 'show') {
            $id = (string)($normalizedArgs[0] ?? '');
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
            'count' => count($formattedEntries),
            'total' => count($entries),
            'entry' => $entry,
            'detail' => $detail,
            'recipient' => $recipient,
            'content' => $content,
        ];

        $messages = [];
        $context = $this->kernel->buildBridgeContext($messages, $vars);
        $result = $this->bridges->runFile($path, $context);
        $output = $result->output();

        if ($output === '' && $messages !== []) {
            $output = implode(PHP_EOL, $messages);
        }

        return $output;
    }

    private function normalizeArgs(array $args, string $resource): array
    {
        $filtered = [];
        $resourcePlural = Str::pluralize($resource);
        foreach ($args as $arg) {
            $value = trim((string)$arg);
            if ($value === '') {
                continue;
            }
            $lower = strtolower($value);
            if (in_array($lower, ['new', $resource, $resourcePlural], true)) {
                continue;
            }
            $filtered[] = $value;
        }

        return $filtered;
    }

    private function parseRecipientAndContent(array $args): array
    {
        if ($args === []) {
            return ['user', ''];
        }

        if (count($args) === 1) {
            return ['user', (string)$args[0]];
        }

        $recipient = (string)$args[0];
        $content = (string)$args[count($args) - 1];

        return [$recipient !== '' ? $recipient : 'user', $content];
    }

    private function formatEntries(array $entries): array
    {
        $lines = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                $lines[] = (string)$entry;
                continue;
            }
            $lines[] = $this->formatEntry($entry);
        }

        return $lines;
    }

    private function formatEntry(array $entry): string
    {
        $id = $entry['id'] ?? '';
        $recipient = $entry['recipient'] ?? 'user';
        $content = $entry['content'] ?? '';
        $preview = $this->preview($content);

        return "{$id} (to {$recipient}): {$preview}";
    }

    private function preview(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '(empty)';
        }

        $max = 64;
        if (strlen($content) <= $max) {
            return $content;
        }

        return substr($content, 0, $max - 3) . '...';
    }
}
