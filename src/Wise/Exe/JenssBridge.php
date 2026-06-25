<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;

class JenssBridge extends Obj implements IBridge
{
    private const JENERATOR_PARSER = \BlueFission\Jenerator\Parsing\JenssParser::class;
    private const JENERATOR_INTERPRETER = \BlueFission\Jenerator\Runtime\Interpreter::class;
    private const JENERATOR_MODULES = \BlueFission\Jenerator\Runtime\ModuleRegistry::class;
    private const JENERATE_PARSER = \BlueFission\Jenerate\Parsing\JenssParser::class;
    private const JENERATE_INTERPRETER = \BlueFission\Jenerate\Runtime\Interpreter::class;
    private const JENERATE_MODULES = \BlueFission\Jenerate\Runtime\ModuleRegistry::class;

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
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        $classes = $this->resolveRuntimeClasses();
        if ($classes === null) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parserClass = $classes['parser'];
        $interpreterClass = $classes['interpreter'];
        $moduleRegistryClass = $classes['modules'];

        try {
            $parser = new $parserClass();
            $ast = $parser->parseFile($path);

            $io = new JenssIo($context);
            $modulePaths = $this->modulePaths($context);
            $modules = $modulePaths !== []
                ? new $moduleRegistryClass($modulePaths)
                : null;
            $this->loadWiseResources($modules);

            $interpreter = new $interpreterClass($io, null, $modules);
            $interpreter->run($ast);

            $result = BridgeResult::success(implode(PHP_EOL, $io->messages()), [
                'path' => $path,
                'messages' => $io->messages(),
                'prompts' => $io->prompts(),
            ]);
            $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
            return $result;
        } catch (\Throwable $e) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path, 'error' => $e->getMessage()], src: $this));
            return BridgeResult::failure('JenSS execution failed: ' . $e->getMessage(), [
                'path' => $path,
                'exception' => $e::class,
            ]);
        }
    }

    public function runSource(string $source, BridgeContext $context, ?string $path = null): BridgeResult
    {
        $path = $path ?? 'wise://inline.jss';
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        $classes = $this->resolveRuntimeClasses();
        if ($classes === null) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('JenSS interpreter is not available in this environment.');
        }

        $parserClass = $classes['parser'];
        $interpreterClass = $classes['interpreter'];
        $moduleRegistryClass = $classes['modules'];

        try {
            $parser = new $parserClass();
            $ast = $parser->parse($source, $path);

            $io = new JenssIo($context);
            $modulePaths = $this->modulePaths($context);
            $modules = $modulePaths !== []
                ? new $moduleRegistryClass($modulePaths)
                : null;
            $this->loadWiseResources($modules);

            $interpreter = new $interpreterClass($io, null, $modules);
            $interpreter->run($ast);

            $result = BridgeResult::success(implode(PHP_EOL, $io->messages()), [
                'path' => $path,
                'messages' => $io->messages(),
                'prompts' => $io->prompts(),
            ]);
            $this->dispatch(Event::COMPLETE, new Meta(data: ['path' => $path], src: $this));
            return $result;
        } catch (\Throwable $e) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path, 'error' => $e->getMessage()], src: $this));
            return BridgeResult::failure('JenSS execution failed: ' . $e->getMessage(), [
                'path' => $path,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function modulePaths(BridgeContext $context): array
    {
        $paths = $context->basePaths();
        $resourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'resources';
        if (!in_array($resourcePath, $paths, true)) {
            $paths[] = $resourcePath;
        }

        $vendorModules = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'jenerator' . DIRECTORY_SEPARATOR . 'modules';
        if (is_dir($vendorModules) && !in_array($vendorModules, $paths, true)) {
            $paths[] = $vendorModules;
        }

        return $paths;
    }

    private function loadWiseResources(?object $modules): void
    {
        if (!$modules || !method_exists($modules, 'resources')) {
            return;
        }

        $resourceFile = __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'wise_resources.map';
        if (!is_file($resourceFile)) {
            return;
        }

        $modules->resources()->load('wise', 'wise_resources');
    }

    /**
     * @return array<string, class-string>|null
     */
    private function resolveRuntimeClasses(): ?array
    {
        $candidates = [
            [
                'parser' => self::JENERATOR_PARSER,
                'interpreter' => self::JENERATOR_INTERPRETER,
                'modules' => self::JENERATOR_MODULES,
            ],
            [
                'parser' => self::JENERATE_PARSER,
                'interpreter' => self::JENERATE_INTERPRETER,
                'modules' => self::JENERATE_MODULES,
            ],
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate['parser'])
                && class_exists($candidate['interpreter'])
                && class_exists($candidate['modules'])) {
                return $candidate;
            }
        }

        return null;
    }
}
