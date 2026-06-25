<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cli\Components\Component;
use BlueFission\Wise\Cli\Components\Cursor;
use BlueFission\Wise\Cli\Components\Prompt;
use BlueFission\Wise\Cli\Components\REPL;
use BlueFission\Wise\Cli\Components\Text;
use BlueFission\Wise\Cli\Components\TextOutput;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Wise\Sys\Drivers\IDisplayDriver;
use BlueFission\Wise\Sys\KeyInputManager;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use PHPUnit\Framework\TestCase;

final class CliComponentsTest extends TestCase
{
    public function testComponentAbsolutePositionAggregatesParents(): void
    {
        $parent = new Component(2, 3, 10, 5, '');
        $child = new Component(1, 1, 5, 2, '');
        $child->setParent($parent);

        $this->assertSame(3, $child->getAbsoluteX());
        $this->assertSame(4, $child->getAbsoluteY());
    }

    public function testPromptLengthIncludesPrefix(): void
    {
        $prompt = new Prompt(0, 0, 10, 'hello', 0, true);
        $prompt->setContext(DIRECTORY_SEPARATOR . 'one' . DIRECTORY_SEPARATOR . 'two' . DIRECTORY_SEPARATOR . 'three' . DIRECTORY_SEPARATOR . 'four');

        $this->assertGreaterThan(0, $prompt->getPrefixLength());
        $this->assertGreaterThanOrEqual(mb_strlen('hello'), $prompt->getLength());
    }

    public function testTextOverflowExpandsHeight(): void
    {
        $text = new Text(0, 0, 5, 1, 'hello world', 0, true, false);
        $text->update();

        $this->assertGreaterThan(1, $text->getHeight());
    }

    public function testTextOutputStaticModeSendsOutput(): void
    {
        $driver = new CliTestDisplayDriver();
        $console = $this->makeConsole($driver);
        $console->setDisplayMode(Console::STATIC_MODE);

        $output = new TextOutput(0, 0, 10, 2);
        $output->setConsole($console);
        $output->addLine('hello');

        $this->assertSame(['hello'], $driver->sent);
    }

    public function testCursorDrawStaticModeIsEmpty(): void
    {
        $driver = new CliTestDisplayDriver();
        $console = $this->makeConsole($driver);
        $console->setDisplayMode(Console::STATIC_MODE);

        $cursor = new Cursor(0, 0, 0);
        $cursor->setConsole($console);

        $this->assertSame([], $cursor->draw());
    }

    public function testCursorDrawDynamicModeReturnsLine(): void
    {
        $driver = new CliTestDisplayDriver();
        $console = $this->makeConsole($driver);
        $console->setDisplayMode(Console::DYNAMIC_MODE);

        $cursor = new Cursor(0, 0, 0);
        $cursor->setConsole($console);

        $lines = $cursor->draw();

        $this->assertCount(1, $lines);
        $this->assertIsString($lines[0]);
    }

    public function testReplHandleInputTriggersProcessedEvent(): void
    {
        $driver = new CliTestDisplayDriver();
        $console = $this->makeConsole($driver);

        $repl = new REPL();
        $repl->setConsole($console);

        $received = [];
        $console->when(new Event(Event::PROCESSED), function ($b, Meta $meta) use (&$received) {
            $received[] = $meta->data;
        });

        $repl->handleInput('ping');

        $this->assertSame([['ping']], $received);
    }

    public function testReplNewPromptResetsPrompt(): void
    {
        $repl = new REPL();

        $promptProperty = new \ReflectionProperty(REPL::class, '_prompt');
        $promptProperty->setAccessible(true);
        $before = $promptProperty->getValue($repl);

        $repl->newPrompt();

        $after = $promptProperty->getValue($repl);

        $this->assertSame($before, $after);
        $this->assertInstanceOf(Prompt::class, $after);
        $this->assertTrue($after->getActive());
    }

    private function makeConsole(CliTestDisplayDriver $driver): Console
    {
        return new Console(new DisplayManager($driver), new KeyInputManager());
    }
}

final class CliTestDisplayDriver implements IDisplayDriver
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

