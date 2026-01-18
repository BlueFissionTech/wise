<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Cli\Console;

class BridgeContext extends Obj
{
    private ?Kernel $kernel;
    private ?Console $console;
    private array $env;
    private array $basePaths;
    private array $includePaths;
    private $outputHandler;
    private $promptHandler;
    private $resourceResolver;
    private $llm;

    public function __construct(
        ?Kernel $kernel = null,
        ?Console $console = null,
        array $env = [],
        array $basePaths = [],
        array $includePaths = [],
        $outputHandler = null,
        $promptHandler = null,
        $resourceResolver = null,
        $llm = null
    ) {
        parent::__construct();
        $this->kernel = $kernel;
        $this->console = $console;
        $this->env = $env;
        $this->basePaths = $basePaths;
        $this->includePaths = $includePaths;
        $this->outputHandler = $outputHandler;
        $this->promptHandler = $promptHandler;
        $this->resourceResolver = $resourceResolver;
        $this->llm = $llm;
    }

    public function kernel(): ?Kernel
    {
        return $this->kernel;
    }

    public function console(): ?Console
    {
        return $this->console;
    }

    /**
     * @return array<string, mixed>
     */
    public function env(): array
    {
        return $this->env;
    }

    /**
     * @return array<int, string>
     */
    public function basePaths(): array
    {
        return $this->basePaths;
    }

    /**
     * @return array<int, string>
     */
    public function includePaths(): array
    {
        return $this->includePaths;
    }

    public function output(string $message): void
    {
        if (is_callable($this->outputHandler)) {
            call_user_func($this->outputHandler, $message);
            $this->dispatch(Event::SENT, new Meta(data: ['message' => $message], src: $this));
            return;
        }

        if ($this->console) {
            $this->console->output($message, 'system');
            $this->dispatch(Event::SENT, new Meta(data: ['message' => $message], src: $this));
        }
    }

    public function prompt(string $message): string
    {
        if (is_callable($this->promptHandler)) {
            $response = (string)call_user_func($this->promptHandler, $message);
            $this->dispatch(Event::MESSAGE, new Meta(data: [
                'message' => $message,
                'response' => $response,
            ], src: $this));
            return $response;
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveResource(string $key): ?array
    {
        if (!is_callable($this->resourceResolver)) {
            return null;
        }

        $result = call_user_func($this->resourceResolver, $key);
        return is_array($result) ? $result : null;
    }

    public function llm()
    {
        return $this->llm;
    }
}
