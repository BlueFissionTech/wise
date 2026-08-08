<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Nav\WiseSynthetiqSamples;
use PHPUnit\Framework\TestCase;

final class SynthetiqSamplesTest extends TestCase
{
    public function testWiseSamplesIncludeCliGuidanceIntents(): void
    {
        $dialogue = WiseSynthetiqSamples::dialogue();
        $boosts = WiseSynthetiqSamples::intentBoosts();

        $this->assertArrayHasKey('wise.command.discovery', $dialogue);
        $this->assertArrayHasKey('wise.weather.question', $dialogue);
        $this->assertArrayHasKey('wise.todo.discovery', $dialogue);
        $this->assertSame(25, $boosts['wise.command.discovery']['priority']);
    }
}
