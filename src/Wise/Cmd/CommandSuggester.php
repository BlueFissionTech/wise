<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Services\Application as App;

class CommandSuggester
{
    private CommandParser $parser;
    private array $commands = [];
    private array $resourceCommands = [];
    private array $resources = [];
    private array $verbs = [];

    public function __construct(?CommandParser $parser = null)
    {
        $this->parser = $parser ?? new CommandParser();
        $this->refresh();
    }

    public function hint(string $input): string
    {
        $input = trim($input);
        $this->refresh();

        if ($input === '') {
            return 'tab: list all resources';
        }

        $command = $this->parser->parse($input);
        $tokens = preg_split('/\s+/', $input);
        $lastToken = strtolower((string)($tokens[count($tokens) - 1] ?? ''));

        if (!$command->verb && $lastToken !== '') {
            $verb = $this->bestMatch($lastToken, $this->verbs);
            if ($verb) {
                return "tab: {$verb} <resource>";
            }
        }

        if ($command->verb && empty($command->resources)) {
            $resourceHints = $this->suggestResources($lastToken, 3);
            if ($resourceHints !== []) {
                $suggestions = array_map(fn($resource) => "{$command->verb} {$resource}", $resourceHints);
                return 'tab: ' . implode(' | ', $suggestions);
            }

            return "tab: {$command->verb} <resource>";
        }

        if (!$command->verb && !empty($command->resources)) {
            $resource = (string)$command->resources[0];
            $verbs = $this->verbsForResource($resource);
            if ($verbs !== []) {
                $verbs = array_slice($verbs, 0, 3);
                $suggestions = array_map(fn($verb) => "{$verb} {$resource}", $verbs);
                return 'tab: ' . implode(' | ', $suggestions);
            }
        }

        $matches = $this->rankMatches($input, $this->commands, 3, 0.6);
        if ($matches !== []) {
            return 'tab: ' . implode(' | ', $matches);
        }

        return '';
    }

    public function complete(string $input): ?string
    {
        $input = trim($input);
        $this->refresh();

        if ($input === '') {
            return 'list all resources';
        }

        $command = $this->parser->parse($input);
        $tokens = preg_split('/\s+/', $input);
        $lastToken = strtolower((string)($tokens[count($tokens) - 1] ?? ''));

        if (!$command->verb && $lastToken !== '') {
            $verb = $this->bestMatch($lastToken, $this->verbs);
            return $verb ? "{$verb} " : null;
        }

        if ($command->verb && empty($command->resources)) {
            $resourceHints = $this->suggestResources($lastToken, 1);
            if ($resourceHints !== []) {
                return trim("{$command->verb} {$resourceHints[0]}");
            }
        }

        if (!$command->verb && !empty($command->resources)) {
            $resource = (string)$command->resources[0];
            $verbs = $this->verbsForResource($resource);
            if ($verbs !== []) {
                return trim("{$verbs[0]} {$resource}");
            }
        }

        $matches = $this->rankMatches($input, $this->commands, 1, 0.6);

        return $matches[0] ?? null;
    }

    private function refresh(): void
    {
        $this->verbs = $this->parser->getSystemVerbs();
        $this->resources = $this->parser->getSystemResources();

        $abilities = App::instance()->getAbilities() ?? [];
        $commands = [];
        $resourceCommands = [];

        foreach ($abilities as $resource => $verbs) {
            if (!is_array($verbs)) {
                continue;
            }
            foreach ($verbs as $verb) {
                $verb = (string)$verb;
                $commands[] = trim($verb . ' ' . $resource);
                $resourceCommands[$resource][] = $verb;
            }
        }

        $commands[] = 'list all resources';
        $commands[] = 'list all commands';
        $commands[] = 'help';

        $this->commands = array_values(array_unique(array_filter($commands)));
        $this->resourceCommands = $resourceCommands;
    }

    private function suggestResources(string $token, int $limit): array
    {
        if ($this->resources === []) {
            return [];
        }

        if ($token === 'list') {
            return array_slice(['all resources', 'all commands', 'todo'], 0, $limit);
        }

        if ($token === '' || in_array($token, $this->verbs, true)) {
            return array_slice($this->resources, 0, $limit);
        }

        return $this->rankMatches($token, $this->resources, $limit, 0.4);
    }

    private function verbsForResource(string $resource): array
    {
        if (isset($this->resourceCommands[$resource])) {
            return $this->resourceCommands[$resource];
        }

        return array_slice($this->verbs, 0, 5);
    }

    private function bestMatch(string $input, array $candidates): ?string
    {
        $matches = $this->rankMatches($input, $candidates, 1, 0.5);
        return $matches[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function rankMatches(string $input, array $candidates, int $limit, float $minScore): array
    {
        $ranked = [];
        $input = strtolower($input);

        foreach ($candidates as $candidate) {
            $candidate = (string)$candidate;
            if ($candidate === '') {
                continue;
            }
            if (str_starts_with(strtolower($candidate), $input)) {
                $ranked[$candidate] = max($ranked[$candidate] ?? 0, 1.0);
                continue;
            }
            similar_text($input, strtolower($candidate), $percent);
            $score = $percent / 100;
            if ($score < $minScore) {
                continue;
            }
            $ranked[$candidate] = max($ranked[$candidate] ?? 0, $score);
        }

        arsort($ranked);

        return array_slice(array_keys($ranked), 0, $limit);
    }
}
