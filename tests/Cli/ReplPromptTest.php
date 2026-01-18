<?php

namespace BlueFission\Tests\Cli;

use BlueFission\Wise\Cli\Components\REPL;
use PHPUnit\Framework\TestCase;

final class ReplPromptTest extends TestCase
{
    public function testNewPromptReplacesPromptAndCursor(): void
    {
        $repl = new REPL();

        $textOutput = $this->getProperty($repl, '_textOutput');
        $children = $textOutput->getChildren();
        $this->assertSame(2, $children->count());

        $repl->newPrompt();
        $childrenAfterFirst = $textOutput->getChildren();
        $this->assertSame(2, $childrenAfterFirst->count());

        $repl->newPrompt();
        $childrenAfterSecond = $textOutput->getChildren();
        $this->assertSame(2, $childrenAfterSecond->count());

        $prompt = $this->getProperty($repl, '_prompt');
        $cursor = $this->getProperty($repl, '_cursor');
        $this->assertSame($prompt->getY(), $cursor->getY());
    }

    private function getProperty(object $object, string $name)
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }
}
