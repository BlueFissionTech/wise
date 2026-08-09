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

    /**
     * @return array<int, string>
     */
    public function extensions(): array
    {
        $extensions = [];
        foreach ($this->bridges as $bridge) {
            $extensions = array_merge($extensions, $bridge->extensions());
        }

        $extensions = array_values(array_unique($extensions));
        sort($extensions);

        return $extensions;
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

    public function validateFile(string $path, BridgeContext $context): BridgeResult
    {
        $this->dispatch(Event::STARTED, new Meta(data: [
            'path' => $path,
            'mode' => 'validate',
        ], src: $this));

        $bridge = $this->bridgeForFile($path);
        if (!$bridge instanceof IValidatingBridge) {
            $this->dispatch(Event::FAILURE, new Meta(data: [
                'path' => $path,
                'mode' => 'validate',
            ], src: $this));

            return BridgeResult::failure('No validating bridge registered for file: ' . $path);
        }

        $result = $bridge->validateFile($path, $context);
        $this->dispatch($result->successFlag() ? Event::COMPLETE : Event::FAILURE, new Meta(data: [
            'path' => $path,
            'bridge' => $bridge->name(),
            'mode' => 'validate',
        ], src: $this));

        return $result;
    }
}
