<?php

namespace BlueFission\Wise\Exe;

class JenssBridge implements IBridge
{
    public function name(): string
    {
        return 'jenss';
    }

    public function extensions(): array
    {
        return ['jss', 'jen'];
    }

    public function canHandleFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $this->extensions(), true);
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        if (!class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\Interpreter::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\ModuleRegistry::class)) {
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parser = new \BlueFission\Jenerate\Parsing\JenssParser();
        $ast = $parser->parseFile($path);

        $io = new JenssIo($context);
        $modulePaths = $context->basePaths();
        $modules = $modulePaths !== []
            ? new \BlueFission\Jenerate\Runtime\ModuleRegistry($modulePaths)
            : null;

        $interpreter = new \BlueFission\Jenerate\Runtime\Interpreter($io, null, $modules);
        $interpreter->run($ast);

        return BridgeResult::success(implode(PHP_EOL, $io->messages()), [
            'path' => $path,
            'messages' => $io->messages(),
            'prompts' => $io->prompts(),
        ]);
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        if (!class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\Interpreter::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\ModuleRegistry::class)) {
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parser = new \BlueFission\Jenerate\Parsing\JenssParser();
        $ast = $parser->parse($source, $path);

        $io = new JenssIo($context);
        $modulePaths = $context->basePaths();
        $modules = $modulePaths !== []
            ? new \BlueFission\Jenerate\Runtime\ModuleRegistry($modulePaths)
            : null;

        $interpreter = new \BlueFission\Jenerate\Runtime\Interpreter($io, null, $modules);
        $interpreter->run($ast);

        return BridgeResult::success(implode(PHP_EOL, $io->messages()), [
            'path' => $path,
            'messages' => $io->messages(),
            'prompts' => $io->prompts(),
        ]);
    }
}
