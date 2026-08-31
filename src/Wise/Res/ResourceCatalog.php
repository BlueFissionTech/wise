<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

class ResourceCatalog extends Obj
{
    public const AVAILABLE = 'available';
    public const UNAVAILABLE = 'unavailable';
    public const UNKNOWN = 'unknown';

    private const RESOURCES = [
        'action' => ActionResource::class,
        'ai' => AIResource::class,
        'api' => APIResource::class,
        'calc' => CalculatorResource::class,
        'command' => CommandResource::class,
        'entity' => EntityResource::class,
        'feature' => FeatureResource::class,
        'file' => FileResource::class,
        'function' => FunctionResource::class,
        'goal' => GoalResource::class,
        'howto' => HowToResource::class,
        'info' => EncyclopediaResource::class,
        'news' => NewsResource::class,
        'note' => NoteResource::class,
        'queue' => QueueResource::class,
        'profile_step' => ProfileStepResource::class,
        'schedule' => ScheduleResource::class,
        'search' => SearchResource::class,
        'stack' => StackResource::class,
        'step' => StepResource::class,
        'system' => SystemResource::class,
        'todo' => TodoResource::class,
        'variable' => VariableResource::class,
        'weather' => WeatherResource::class,
        'website' => WebBrowserResource::class,
    ];

    private const ALIASES = [
        'calculator' => 'calc',
        'encyclopedia' => 'info',
        'filemanager' => 'file',
        'functions' => 'function',
        'goals' => 'goal',
        'profile_steps' => 'profile_step',
        'web' => 'website',
    ];

    private const UNAVAILABLE_RESOURCES = [
        'message' => 'not_implemented',
        'resource' => 'not_implemented',
        'transcript' => 'not_implemented',
    ];

    private Arr $resources;
    private Arr $aliases;
    private Arr $unavailableResources;

    public function __construct(
        array $resources = [],
        array $aliases = [],
        array $unavailableResources = []
    ) {
        parent::__construct();
        $this->resources = Arr::make($this->normalizeMap(Arr::merge(self::RESOURCES, $resources)));
        $this->aliases = Arr::make($this->normalizeMap(Arr::merge(self::ALIASES, $aliases), true));
        $this->unavailableResources = Arr::make($this->normalizeMap(Arr::merge(
            self::UNAVAILABLE_RESOURCES,
            $unavailableResources
        )));
    }

    public function definitions(): array
    {
        return $this->resources->toArray();
    }

    public function aliases(): array
    {
        return $this->aliases->toArray();
    }

    public function unavailable(): array
    {
        return $this->unavailableResources->toArray();
    }

    public function identifiers(bool $includeUnavailable = false): array
    {
        $identifiers = Arr::keys($this->definitions());
        if ($includeUnavailable) {
            $identifiers = Arr::merge($identifiers, Arr::keys($this->unavailable()));
        }

        return Arr::make($identifiers)->unique()->sort()->toArray();
    }

    public function classFor(string $identifier): ?string
    {
        $identifier = $this->canonicalIdentifier($identifier);
        $definitions = $this->definitions();

        return Arr::hasKey($definitions, $identifier) ? $definitions[$identifier] : null;
    }

    public function status(string $identifier): string
    {
        $identifier = $this->canonicalIdentifier($identifier);
        if (Arr::hasKey($this->definitions(), $identifier)) {
            return self::AVAILABLE;
        }
        if (Arr::hasKey($this->unavailable(), $identifier)) {
            return self::UNAVAILABLE;
        }

        return self::UNKNOWN;
    }

    public function describe(string $identifier): array
    {
        $requested = $this->normalize($identifier);
        $canonical = $this->canonicalIdentifier($requested);
        $status = $this->status($canonical);
        $unavailable = $this->unavailable();

        return [
            'identifier' => $requested,
            'canonical_identifier' => $canonical,
            'status' => $status,
            'class' => $status === self::AVAILABLE ? $this->classFor($canonical) : null,
            'reason' => $status === self::UNAVAILABLE ? $unavailable[$canonical] : null,
        ];
    }

    public function missingClasses(): array
    {
        $missing = Arr::make();
        foreach ($this->definitions() as $identifier => $class) {
            if (!class_exists($class)) {
                $missing[$identifier] = $class;
            }
        }

        return $missing->toArray();
    }

    private function canonicalIdentifier(string $identifier): string
    {
        $identifier = $this->normalize($identifier);
        $aliases = $this->aliases();

        return Arr::hasKey($aliases, $identifier) ? $aliases[$identifier] : $identifier;
    }

    private function normalize(string $identifier): string
    {
        $identifier = Str::lower(Str::trim($identifier));
        $identifier = Str::replace($identifier, '-', '_');

        return Str::replace($identifier, ' ', '_');
    }

    private function normalizeMap(array $map, bool $normalizeValues = false): array
    {
        $normalized = Arr::make();
        foreach ($map as $identifier => $value) {
            $normalized[$this->normalize((string)$identifier)] = $normalizeValues
                ? $this->normalize((string)$value)
                : $value;
        }

        return $normalized->toArray();
    }
}
