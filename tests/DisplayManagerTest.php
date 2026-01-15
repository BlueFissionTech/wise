<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use PHPUnit\Framework\TestCase;

final class DisplayManagerTest extends TestCase
{
    public function testSendDelegatesToDriver(): void
    {
        $driver = new TestDisplayDriver();
        $manager = new DisplayManager($driver);

        $manager->send('hello');

        $this->assertSame([['hello', null, null]], $driver->handles);
        $this->assertSame(['hello'], $driver->sent);
        $this->assertSame(1, $driver->flushCount);
    }

    public function testDisplayDelegatesToDriver(): void
    {
        $driver = new TestDisplayDriver();
        $manager = new DisplayManager($driver);

        $manager->display('status', ['info', 'blue']);

        $this->assertSame([['status', 'info', 'blue']], $driver->handles);
    }

    public function testDisplayHandlesAssociativeArgs(): void
    {
        $driver = new TestDisplayDriver();
        $manager = new DisplayManager($driver);

        $manager->display('status', ['error' => 'red']);

        $this->assertSame([['status', 'error', 'red']], $driver->handles);
    }
}

final class TestDisplayDriver implements IDisplayDriver
{
    public array $handles = [];
    public array $sent = [];
    public int $flushCount = 0;
    private array $content = [];

    public function handle($data, $type = null, $style = null): void
    {
        $this->handles[] = [$data, $type, $style];
        $this->content[] = $data;
    }

    public function send($data): void
    {
        $this->sent[] = $data;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function flush(): void
    {
        $this->flushCount++;
        $this->content = [];
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
