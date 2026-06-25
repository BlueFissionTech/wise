<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Sys\FileSystemManager;

class VibeBridge extends Obj implements IBridge
{
    public function name(): string
    {
        return 'vibe';
    }

    public function extensions(): array
    {
        return ['vibe', 'vibrato'];
    }

    public function canHandleFile(string $path): bool
    {
        $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        return Arr::has($this->extensions(), $ext, true);
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        if (!$this->ensureVibratoReader()) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            $llm = new NullLlmClient();
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
        $this->applyContextVars($reader, $context->vars());
        $reader->inputFile($path);

        $vars = $reader->run();
        $result = BridgeResult::success($reader->output(), [
            'path' => $path,
            'variables' => $vars,
        ]);
        $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
        return $result;
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        if (!$this->ensureVibratoReader()) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            $llm = new NullLlmClient();
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
        $this->applyContextVars($reader, $context->vars());
        $reader->input($source);

        $vars = $reader->run();
        $result = BridgeResult::success($reader->output(), [
            'path' => $path,
            'variables' => $vars,
        ]);
        $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
        return $result;
    }

    private function applyContextVars(object $reader, array $vars): void
    {
        if ($vars === []) {
            return;
        }

        try {
            $property = new \ReflectionProperty($reader, 'vars');
            $property->setAccessible(true);
            $property->setValue($reader, $vars);
        } catch (\Throwable $e) {
            // Ignore if the reader doesn't expose vars.
        }
    }

    private function ensureVibratoReader(): bool
    {
        if (class_exists(\BlueFission\Vibrato\Reader::class)) {
            return true;
        }

        $base = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'bluefission'
            . DIRECTORY_SEPARATOR . 'vibrato';

        $autoload = $base . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (FileSystemManager::pathExists($autoload)) {
            require_once $autoload;
        }

        $readerFile = $base
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Vibrato'
            . DIRECTORY_SEPARATOR . 'Reader.php';
        if (FileSystemManager::pathExists($readerFile)) {
            require_once $readerFile;
        }

        return class_exists(\BlueFission\Vibrato\Reader::class);
    }
}
