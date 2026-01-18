<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;

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
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $this->extensions(), true);
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter requires an LLM client.');
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
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
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('Vibe interpreter requires an LLM client.');
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
        $reader->input($source);

        $vars = $reader->run();
        $result = BridgeResult::success($reader->output(), [
            'path' => $path,
            'variables' => $vars,
        ]);
        $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
        return $result;
    }
}
