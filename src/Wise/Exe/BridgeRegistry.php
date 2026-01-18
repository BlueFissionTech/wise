<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;

class BridgeRegistry extends Obj
{
    /** @var array<int, IBridge> */
    private array $bridges = [];

    public function register(IBridge $bridge): void
    {
        $this->dispatch(Event::CHANGE, new Meta(data: ['bridge' => $bridge->name()], src: $this));
        $this->bridges[] = $bridge;
    }

    public function bridgeForFile(string $path): ?IBridge
    {
        foreach ($this->bridges as $bridge) {
            if ($bridge->canHandleFile($path)) {
                return $bridge;
            }
        }

        return null;
    }

    public function runFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: ['path' => $path], src: $this));
        $bridge = $this->bridgeForFile($path);
        if (!$bridge) {
            $this->dispatch(Event::FAILURE, new Meta(data: ['path' => $path], src: $this));
            return BridgeResult::failure('No bridge registered for file: ' . $path);
        }

        $result = $bridge->runFile($path, $context);
        $this->dispatch($result->successFlag() ? Event::COMPLETE : Event::FAILURE, new Meta(data: [
            'path' => $path,
            'bridge' => $bridge->name(),
        ], src: $this));

        return $result;
    }
}
