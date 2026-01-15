<?php

namespace BlueFission\Wise\Exe;

class VibeBridge implements IBridge
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
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            return BridgeResult::failure('Vibe interpreter requires an LLM client.');
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
        $reader->inputFile($path);

        $vars = $reader->run();
        return BridgeResult::success($reader->output(), [
            'path' => $path,
            'variables' => $vars,
        ]);
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        if (!class_exists(\BlueFission\Vibrato\Reader::class)) {
            return BridgeResult::failure('Vibe interpreter is not available in this environment.');
        }

        $llm = $context->llm();
        if ($llm === null) {
            return BridgeResult::failure('Vibe interpreter requires an LLM client.');
        }

        $reader = new \BlueFission\Vibrato\Reader($llm);
        $includePaths = $context->includePaths();
        if ($includePaths !== []) {
            $reader->setIncludePaths($includePaths);
        }
        $reader->input($source);

        $vars = $reader->run();
        return BridgeResult::success($reader->output(), [
            'path' => $path,
            'variables' => $vars,
        ]);
    }
}
