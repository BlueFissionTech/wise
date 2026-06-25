<?php

namespace BlueFission\Wise\Res;

use BlueFission\Obj;
use BlueFission\Services\Application as App;
use BlueFission\Str;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Exe\BridgeRegistry;

class ScriptedResourceRegistry extends Obj
{
    private Kernel $kernel;
    private BridgeRegistry $bridges;
    private string $rootPath;
    private string $resourceDir = 'sys' . DIRECTORY_SEPARATOR . 'res';
    private ScriptedResourceRunner $runner;
    private array $registered = [];

    public function __construct(Kernel $kernel, BridgeRegistry $bridges, string $rootPath, ?ScriptedResourceRunner $runner = null)
    {
        parent::__construct();
        $this->kernel = $kernel;
        $this->bridges = $bridges;
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->runner = $runner ?? new ScriptedResourceRunner($kernel, $bridges);
    }

    public function register(): void
    {
        $resourcePath = $this->rootPath . DIRECTORY_SEPARATOR . $this->resourceDir;
        if (!is_dir($resourcePath)) {
            return;
        }

        $files = glob($resourcePath . DIRECTORY_SEPARATOR . '*.vibe');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name === '' || isset($this->registered[$name])) {
                continue;
            }

            $definition = $this->buildDefinition($name, $file);
            $this->registerResource($definition);
            $this->registered[$name] = true;
        }
    }

    private function buildDefinition(string $name, string $path): ScriptedResourceDefinition
    {
        $actions = ['list', 'show', 'send', 'help'];
        $description = Str::capitalize($name) . ' resource powered by Vibe.';
        $hint = "Use the {$name} resource to manage {$name} entries via scripted flows.";

        return new ScriptedResourceDefinition($name, $path, $actions, $description, $hint);
    }

    private function registerResource(ScriptedResourceDefinition $definition): void
    {
        ResourceHelper::addResource($definition->name(), $definition->description(), $definition->hint());

        $resource = new ScriptedResource($definition, $this->runner);
        $app = App::instance();
        $app->delegate($definition->name(), $resource);

        foreach ($definition->actions() as $action) {
            $app->register($definition->name(), $action, [$resource, 'handle']);
        }
    }
}
