<?php

namespace BlueFission\Tests;

use BlueFission\Data\Storage\Storage;
use BlueFission\Wise\Nav\INavigator;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Services\Application as App;
use PHPUnit\Framework\TestCase;

final class CommandProcessorTest extends TestCase
{
    public function testEmptyInputReturnsPrompt(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $result = $processor->handle('');

        $this->assertSame('No command entered.', $result);
    }

    public function testUnknownResourceReportsNotFound(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $result = $processor->handle('list file');

        $this->assertSame('Resource not found', $result);
    }

    public function testMissingResourcePromptsForResource(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $result = $processor->handle('list');

        $this->assertSame(
            'No registered resource specified in the command. Which resource do you want to list? [files, todos, or variables]',
            $result
        );
    }

    public function testHelpWithNoResourceReturnsPrompt(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $result = $processor->handle('help');

        $this->assertSame(
            'How can I help? Type `list all resources` to see what you have access to.',
            $result
        );
    }

    public function testConfirmCommandAcceptsYes(): void
    {
        $storage = $this->makeStorage();
        $storage->confirmCmd = (object) [
            'verb' => 'list',
            'resources' => ['file'],
            'args' => [],
        ];

        $processor = new CommandProcessor($storage);
        $result = $processor->handle('yes');

        $this->assertSame('Resource not found', $result);
    }

    public function testConfirmCommandAcceptsNo(): void
    {
        $storage = $this->makeStorage();
        $storage->confirmCmd = (object) [
            'verb' => 'list',
            'resources' => ['file'],
            'args' => [],
        ];

        $processor = new CommandProcessor($storage);
        $result = $processor->handle('no');

        $this->assertSame('Command cancelled.', $result);
        $this->assertNull($storage->confirmCmd ?? null);
    }

    public function testPronounResolutionUsesLastResource(): void
    {
        $storage = $this->makeStorage();
        $storage->lastResource = 'file';

        $processor = new CommandProcessor($storage);
        $result = $processor->handle('list it');

        $this->assertSame('Resource not found', $result);
    }

    public function testNavigatorResponseOverridesFallback(): void
    {
        $promptEngine = new class implements INavigator {
            public function process(string $input): string
            {
                return 'synthetiq-response';
            }
        };

        $processor = new CommandProcessor($this->makeStorage(), null, $promptEngine);
        $result = $processor->handle('unmatched input');

        $this->assertSame('synthetiq-response', $result);
    }

    public function testNavigatorEmptyResponseFallsBack(): void
    {
        $promptEngine = new class implements INavigator {
            public function process(string $input): string
            {
                return '';
            }
        };

        $processor = new CommandProcessor($this->makeStorage(), null, $promptEngine);
        $result = $processor->handle('unmatched input');

        $this->assertSame('I did not understand your command.', $result);
    }

    public function testRepeatedCommandTriggersConfirmation(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $processor->handle('list file');
        $processor->handle('list file');
        $processor->handle('list file');
        $result = $processor->handle('list file');

        $this->assertSame(
            "You've submitted this command over 3 times in a row. Are you sure you want to run it again? [yes/no]",
            $result
        );
    }

    public function testSuggestCommandsOffersClosestMatch(): void
    {
        $app = App::instance();
        $app->register('testfile', 'list', function () {
            return null;
        });

        $processor = new CommandProcessor($this->makeStorage());
        $result = $processor->handle('lst testfile');

        $this->assertSame("Did you mean 'list testfile'? [yes/no]", $result);
    }

    public function testUnknownGrammarErrorIsSuppressed(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $result = $processor->handle('gibberish input');

        $this->assertSame('I did not understand your command.', $result);
    }

    public function testQuestionInputFallsBackToSearch(): void
    {
        $processor = new CommandProcessor($this->makeStorage());

        $processor->handle('list file');
        $result = $processor->handle('list it');

        $this->assertSame('Resource not found', $result);
    }

    private function makeStorage(): Storage
    {
        $storage = new Storage();
        $source = new \ReflectionProperty(Storage::class, '_source');
        $source->setAccessible(true);
        $source->setValue($storage, []);

        $storage->activate();

        return $storage;
    }
}
