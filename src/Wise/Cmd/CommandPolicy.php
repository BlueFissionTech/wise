<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use InvalidArgumentException;

final class CommandPolicy extends Obj
{
    public const RESOURCE = 'resource';
    public const NATIVE = 'native';
    public const SCRIPT = 'script';

    private Arr $allowlist;

    public function __construct(array $allowlist)
    {
        parent::__construct();

        $normalized = Arr::make();
        foreach ($allowlist as $identifier) {
            $identifier = $this->normalize((string)$identifier);
            if (!$this->valid($identifier)) {
                throw new InvalidArgumentException('Command policy identifiers must use a canonical route prefix.');
            }
            $normalized->push($identifier);
        }

        $this->allowlist = $normalized->unique()->sort();
    }

    public static function resource(string $resource, string $action): string
    {
        return self::identifier(self::RESOURCE, $resource . '.' . $action);
    }

    public static function native(string $command): string
    {
        return self::identifier(self::NATIVE, $command);
    }

    public static function script(string $extension): string
    {
        return self::identifier(self::SCRIPT, $extension);
    }

    public function allowlist(): array
    {
        return $this->allowlist->toArray();
    }

    public function allows(string $identifier): bool
    {
        $identifier = $this->normalize($identifier);
        foreach ($this->allowlist as $allowed) {
            if (Str::match((string)$allowed, $identifier)) {
                return true;
            }
            if (Str::endsWith((string)$allowed, '*')
                && Str::startsWith(
                    $identifier,
                    Str::make((string)$allowed)->sub(0, -1)
                )) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return ['allowlist' => $this->allowlist()];
    }

    private static function identifier(string $route, string $target): string
    {
        $route = Str::make($route)->trim()->lower()->val();
        $target = Str::make($target)
            ->trim()
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->val();

        return $route . ':' . $target;
    }

    private function normalize(string $identifier): string
    {
        return Str::make($identifier)
            ->trim()
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->val();
    }

    private function valid(string $identifier): bool
    {
        return Str::matches($identifier, '/^(resource:[a-z0-9_*.]+\.[a-z0-9_*]+|native:[a-z0-9_*]+|script:[a-z0-9_*]+)$/');
    }
}
