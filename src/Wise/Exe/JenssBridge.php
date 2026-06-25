<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Sys\DirectoryManager;
use BlueFission\Wise\Sys\FileSystemManager;

class JenssBridge extends Obj implements IBridge
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
        $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        return Arr::has($this->extensions(), $ext, true);
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        if (!class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\Interpreter::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\ModuleRegistry::class)) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parser = new \BlueFission\Jenerate\Parsing\JenssParser();
        $ast = $parser->parseFile($path);

        $io = new JenssIo($context);
        $modulePaths = $this->modulePaths($context);
        $modules = $modulePaths !== []
            ? new \BlueFission\Jenerate\Runtime\ModuleRegistry($modulePaths)
            : null;
        $this->loadWiseResources($modules);

        $interpreter = new \BlueFission\Jenerate\Runtime\Interpreter($io, null, $modules);
        $interpreter->run($ast);

        $result = BridgeResult::success(implode(PHP_EOL, $io->messages()), [
            'path' => $path,
            'messages' => $io->messages(),
            'prompts' => $io->prompts(),
        ]);
        $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
        return $result;
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        if (!class_exists(\BlueFission\Jenerate\Parsing\JenssParser::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\Interpreter::class)
            || !class_exists(\BlueFission\Jenerate\Runtime\ModuleRegistry::class)) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parser = new \BlueFission\Jenerate\Parsing\JenssParser();
        $ast = $parser->parse($source, $path);

        $io = new JenssIo($context);
        $modulePaths = $this->modulePaths($context);
        $modules = $modulePaths !== []
            ? new \BlueFission\Jenerate\Runtime\ModuleRegistry($modulePaths)
            : null;
        $this->loadWiseResources($modules);

        $interpreter = new \BlueFission\Jenerate\Runtime\Interpreter($io, null, $modules);
        $interpreter->run($ast);

        $result = BridgeResult::success(implode(PHP_EOL, $io->messages()), [
            'path' => $path,
            'messages' => $io->messages(),
            'prompts' => $io->prompts(),
        ]);
        $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function modulePaths(BridgeContext $context): array
    {
        $paths = $context->basePaths();
        $resourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'resources';
        if (!Arr::has($paths, $resourcePath, true)) {
            $paths[] = $resourcePath;
        }

        $vendorModules = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'jenerator' . DIRECTORY_SEPARATOR . 'modules';
        if (DirectoryManager::pathExists($vendorModules) && !Arr::has($paths, $vendorModules, true)) {
            $paths[] = $vendorModules;
        }

        return $paths;
    }

    private function loadWiseResources(?\BlueFission\Jenerate\Runtime\ModuleRegistry $modules): void
    {
        if (!$modules) {
            return;
        }

        $resourceFile = __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'wise_resources.map';
        if (!FileSystemManager::pathExists($resourceFile)) {
            return;
        }

        $modules->resources()->load('wise', 'wise_resources');
    }
}
