<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Services\Application as App;
use BlueFission\Str;
use BlueFission\Val;

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
        $input = Str::make($input)->trim()->val();
        $this->refresh();

        if (Str::isEmpty($input)) {
            return 'try: list all resources | help';
        }

        $command = $this->parser->parse($input);
        $tokens = preg_split('/\s+/', $input);
        $lastToken = Str::make((string)($tokens[Arr::count($tokens) - 1] ?? ''))->lower()->val();

        if (Str::isEmpty((string)$command->verb) && !Str::isEmpty($lastToken)) {
            $verb = $this->bestMatch($lastToken, $this->verbs);
            if (Val::is($verb)) {
                return "suggest: {$verb} <resource>";
            }
        }

        if (!Str::isEmpty((string)$command->verb) && Arr::isEmpty($command->resources)) {
            $resourceHints = $this->suggestResources($lastToken, 3);
            if ($resourceHints !== []) {
                $suggestions = Arr::make($resourceHints)
                    ->map(fn($resource) => "{$command->verb} {$resource}")
                    ->values()
                    ->val();
                return 'suggest: ' . implode(' | ', $suggestions);
            }

            return "suggest: {$command->verb} <resource>";
        }

        if (Str::isEmpty((string)$command->verb) && Arr::isNotEmpty($command->resources)) {
            $resource = (string)$command->resources[0];
            $verbs = $this->verbsForResource($resource);
            if ($verbs !== []) {
                $verbs = Arr::make($verbs)->slice(0, 3);
                $suggestions = Arr::make($verbs)
                    ->map(fn($verb) => "{$verb} {$resource}")
                    ->values()
                    ->val();
                return 'suggest: ' . implode(' | ', $suggestions);
            }
        }

        $matches = $this->rankMatches($input, $this->commands, 3, 0.6);
        if ($matches !== []) {
            return 'suggest: ' . implode(' | ', $matches);
        }

        return '';
    }

    private function refresh(): void
    {
        $this->verbs = $this->parser->getSystemVerbs();
        $this->resources = $this->parser->getSystemResources();

        $abilities = App::instance()->getAbilities() ?? [];
        $commands = [];
        $resourceCommands = [];

        foreach ($abilities as $resource => $verbs) {
            if (!Arr::is($verbs)) {
                continue;
            }
            foreach ($verbs as $verb) {
                $verb = (string)$verb;
                $commands[] = Str::make($verb . ' ' . $resource)->trim()->val();
                $resourceCommands[$resource][] = $verb;
            }
        }

        $commands[] = 'list all resources';
        $commands[] = 'list all commands';
        $commands[] = 'help';

        $this->commands = Arr::make($commands)
            ->filter(fn($command) => Val::is($command) && !Str::isEmpty((string)$command))
            ->unique()
            ->values()
            ->val();
        $this->resourceCommands = $resourceCommands;
    }

    private function suggestResources(string $token, int $limit): array
    {
        if (Arr::isEmpty($this->resources)) {
            return [];
        }

        if (Str::isEmpty($token) || Arr::has($this->verbs, $token, true)) {
            return Arr::make($this->resources)->slice(0, $limit)->val();
        }

        return $this->rankMatches($token, $this->resources, $limit, 0.4);
    }

    private function verbsForResource(string $resource): array
    {
        if (Arr::hasKey($this->resourceCommands, $resource)) {
            return $this->resourceCommands[$resource];
        }

        return Arr::make($this->verbs)->slice(0, 5);
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
        $input = Str::make($input)->lower()->val();

        foreach ($candidates as $candidate) {
            $candidate = (string)$candidate;
            if (Str::isEmpty($candidate)) {
                continue;
            }
            similar_text($input, Str::make($candidate)->lower()->val(), $percent);
            $score = $percent / 100;
            if ($score < $minScore) {
                continue;
            }
            $ranked[$candidate] = max($ranked[$candidate] ?? 0, $score);
        }

        arsort($ranked);

        return Arr::make($ranked)->keys()->slice(0, $limit)->val();
    }
}
