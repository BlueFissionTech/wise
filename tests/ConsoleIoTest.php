<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Sys\KeyInputManager;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use PHPUnit\Framework\TestCase;

final class ConsoleIoTest extends TestCase
{
    public function testOutputEmitsReceivedEvent(): void
    {
        $console = $this->makeConsole();
        $console->registerInputChannel('system');

        $received = [];
        $console->when(new Event(Event::RECEIVED), function ($b, Meta $meta) use (&$received) {
            $received[] = $meta->data[0];
        });

        $console->output('hello', 'system');

        $this->assertCount(1, $received);
        $this->assertSame('system', $received[0]->channel);
        $this->assertSame('hello', $received[0]->content);
    }

    public function testOutputDefaultsToFirstChannel(): void
    {
        $console = $this->makeConsole();
        $console->registerInputChannel('stdio');

        $received = [];
        $console->when(new Event(Event::RECEIVED), function ($b, Meta $meta) use (&$received) {
            $received[] = $meta->data[0];
        });

        $console->output('ping');

        $this->assertCount(1, $received);
        $this->assertSame('stdio', $received[0]->channel);
        $this->assertSame('ping', $received[0]->content);
    }

    public function testOutputUnknownChannelThrows(): void
    {
        $console = $this->makeConsole();
        $console->registerInputChannel('system');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Input channel not registered');

        $console->output('oops', 'missing');
    }

    private function makeConsole(): Console
    {
        return new Console(new DisplayManager(new ConsoleTestDisplayDriver()), new KeyInputManager());
    }
}

final class ConsoleTestDisplayDriver implements IDisplayDriver
{
    public function handle($data, $type = null, $style = null): void
    {
    }

    public function send($data): void
    {
    }

    public function getContent(): array
    {
        return [];
    }

    public function flush(): void
    {
    }

    public function getTerminalSize(): array
    {
        return [80, 24];
    }

    public function init(): void
    {
    }

    public function update(): void
    {
    }

    public function draw(): void
    {
    }

    public function print(): void
    {
    }

    public function clear(): void
    {
    }

    public function clearScreen(): void
    {
    }
}
