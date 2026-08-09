<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;
use RuntimeException;

class StorageRoot extends Obj
{
    public const ENVIRONMENT_KEY = 'WISE_STORAGE_ROOT';

    private Str $root;

    public function __construct(?string $path = null)
    {
        parent::__construct();
        $this->root = Str::make($this->normalize($this->resolve($path)));
    }

    public function root(): string
    {
        return $this->root->val();
    }

    public function path(string ...$segments): string
    {
        $parts = Arr::make([$this->root()]);

        foreach ($segments as $segment) {
            $segment = Str::make($segment)->trim()->val();
            if (Str::isEmpty($segment)) {
                continue;
            }

            if ($segment === '..' || Str::has($segment, '/') || Str::has($segment, '\\')) {
                throw new RuntimeException('Storage path segments must be relative names.');
            }

            $parts->push($segment);
        }

        return $parts->join(DIRECTORY_SEPARATOR)->val();
    }

    public function prepare(string ...$segments): string
    {
        $path = $this->path(...$segments);

        if (!DirectoryManager::ensurePath($path)
            || !DirectoryManager::pathIsReachable($path)
            || !$this->isWritable($path)) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            throw new RuntimeException("Storage path is unavailable: {$path}");
        }

        $this->dispatch(Event::SUCCESS, new Meta(data: ['path' => $path], src: $this));

        return $path;
    }

    protected function isWritable(string $path): bool
    {
        // DevElation currently exposes directory readability but not writability.
        return is_writable($path);
    }

    private function resolve(?string $path): string
    {
        if (Val::isNotNull($path) && Str::isNotEmpty($path)) {
            return $path;
        }

        $configured = env(self::ENVIRONMENT_KEY);
        if (Str::is($configured) && Str::isNotEmpty($configured)) {
            return $configured;
        }

        if (defined('OPUS_ROOT')) {
            return Arr::make([(string)constant('OPUS_ROOT'), 'storage'])
                ->join(DIRECTORY_SEPARATOR)
                ->val();
        }

        return 'storage';
    }

    private function normalize(string $path): string
    {
        $path = Str::make($path)
            ->trim()
            ->replace('/', DIRECTORY_SEPARATOR)
            ->replace('\\', DIRECTORY_SEPARATOR)
            ->val();

        if (Str::isEmpty($path) || Str::has($path, "\0")) {
            throw new RuntimeException('Storage root must be a valid path.');
        }

        $segments = Str::make($path)->split(DIRECTORY_SEPARATOR);
        if ($segments->has('..')) {
            throw new RuntimeException('Storage root cannot traverse parent directories.');
        }

        $trimmed = rtrim($path, DIRECTORY_SEPARATOR);

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }
}
