<?php

namespace BlueFission\Tests\Cmd;

use BlueFission\Wise\Cmd\CommandSuggester;
use PHPUnit\Framework\TestCase;

final class CommandSuggesterTest extends TestCase
{
    public function testSuggestsDefaultsWhenEmpty(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('');

        $this->assertStringContainsString('list all resources', $hint);
        $this->assertStringContainsString('help', $hint);
    }

    public function testSuggestsVerbForPartialInput(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('lis');

        $this->assertStringContainsString('list', $hint);
    }

    public function testSuggestsResourceForVerbOnly(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('list');

        $this->assertStringContainsString('list', $hint);
    }
}
