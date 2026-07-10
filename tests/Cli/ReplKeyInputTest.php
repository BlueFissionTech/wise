<?php

namespace BlueFission\Tests\Cli;

use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cli\Components\REPL;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Sys\KeyInputManager;
use BlueFission\Wise\Sys\IO\IInputSource;
use PHPUnit\Framework\TestCase;

final class ReplKeyInputTest extends TestCase
{
    public function testTypingUpdatesPromptBuffer(): void
    {
        $input = new SequenceInputSource(['h', 'i', "\n"]);
        $console = $this->makeConsole($input);
        $repl = new REPL();
        $console->addComponent($repl);

        $console->listen();
        $this->assertSame('h', $repl->inputValue());

        $console->listen();
        $this->assertSame('hi', $repl->inputValue());

        $console->listen();
        $this->assertSame('', $repl->inputValue());
    }

    public function testBackspaceRemovesCharacter(): void
    {
        $input = new SequenceInputSource(['t', 'e', 's', 't', "\x7F", "\n"]);
        $console = $this->makeConsole($input);
        $repl = new REPL();
        $console->addComponent($repl);

        $console->listen();
        $console->listen();
        $console->listen();
        $console->listen();
        $console->listen();

        $this->assertSame('tes', $repl->inputValue());

        $console->listen();
        $this->assertSame('', $repl->inputValue());
    }

    public function testHintUpdatesOnInput(): void
    {
        $input = new SequenceInputSource(['list']);
        $console = $this->makeConsole($input);
        $repl = new REPL();
        $console->addComponent($repl);

        $console->listen();

        $this->assertNotSame('', $repl->hintValue());
    }

    public function testTabCompletesPromptBuffer(): void
    {
        $input = new SequenceInputSource(['lis', "\t"]);
        $console = $this->makeConsole($input);
        $repl = new REPL();
        $console->addComponent($repl);

        $console->listen();
        $console->listen();

        $this->assertSame('list ', $repl->inputValue());
    }

    public function testTabWithLongPromptDoesNotTriggerStringOffsetWarnings(): void
    {
        $content = str_repeat('x', 180);
        $input = new SequenceInputSource([$content, "\t"]);
        $console = $this->makeConsole($input);
        $repl = new REPL();
        $console->addComponent($repl);

        $console->listen();
        $console->listen();

        $this->assertSame($content, $repl->inputValue());
    }

    private function makeConsole(IInputSource $input): Console
    {
        $keyInput = new KeyInputManager($input, true);
        $displayDriver = new ReplNullDisplayDriver();
        $console = new Console(new DisplayManager($displayDriver), $keyInput);
        $console->registerInputChannel('stdio');

        return $console;
    }
}

final class SequenceInputSource implements IInputSource
{
    private array $items;
    private int $index = 0;

    public function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    public function read(): ?string
    {
        if (!$this->hasMore()) {
            return null;
        }

        $value = $this->items[$this->index];
        $this->index++;

        return (string)$value;
    }

    public function hasMore(): bool
    {
        return $this->index < count($this->items);
    }
}

final class ReplNullDisplayDriver implements IDisplayDriver
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
