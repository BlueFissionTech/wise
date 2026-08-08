<?php

namespace BlueFission\Tests\Cli;

use BlueFission\Wise\Cli\Components\SplashScreen;
use PHPUnit\Framework\TestCase;

final class SplashScreenTest extends TestCase
{
    public function testGlitchSplashKeepsLogoContent(): void
    {
        $previous = getenv('WISE_SPLASH_GLITCH');
        putenv('WISE_SPLASH_GLITCH=1');

        $lastGlitch = new \ReflectionProperty(SplashScreen::class, '_lastGlitch');
        $lastGlitch->setAccessible(true);
        $lastGlitch->setValue(0);

        try {
            $splash = new SplashScreen();
            $lines = $splash->draw();
        } finally {
            putenv($previous === false ? 'WISE_SPLASH_GLITCH' : 'WISE_SPLASH_GLITCH=' . $previous);
        }

        $workspaceLine = null;
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'Workspace Intelligence Shell Environment')) {
                $workspaceLine = $index;
                break;
            }
        }

        $this->assertNotNull($workspaceLine);
        $this->assertGreaterThan(3, $workspaceLine);
    }
}
