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

        $this->assertStringContainsString('tab:', $hint);
        $this->assertStringContainsString('list all resources', $hint);
    }

    public function testSuggestsVerbForPartialInput(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('lis');

        $this->assertStringContainsString('list', $hint);
    }

    public function testSuggestsVerbForTypoInput(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('hlep');

        $this->assertStringContainsString('help', $hint);
    }

    public function testSuggestsResourceForVerbOnly(): void
    {
        $suggester = new CommandSuggester();
        $hint = $suggester->hint('list');

        $this->assertStringContainsString('list all resources', $hint);
    }

    public function testCompletesPartialVerb(): void
    {
        $suggester = new CommandSuggester();

        $this->assertSame('list ', $suggester->complete('lis'));
    }

    public function testCompletesListToResourceDiscovery(): void
    {
        $suggester = new CommandSuggester();

        $this->assertSame('list all resources', $suggester->complete('list'));
    }
}
