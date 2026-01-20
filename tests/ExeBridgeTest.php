<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Exe\BridgeContext;
use BlueFission\Wise\Exe\BridgeRegistry;
use BlueFission\Wise\Exe\JenssBridge;
use BlueFission\Wise\Exe\VibeBridge;
use PHPUnit\Framework\TestCase;

final class ExeBridgeTest extends TestCase
{
    public function testBridgeRegistrySelectsByExtension(): void
    {
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $registry->register(new VibeBridge());

        $this->assertInstanceOf(JenssBridge::class, $registry->bridgeForFile('script.jss'));
        $this->assertInstanceOf(VibeBridge::class, $registry->bridgeForFile('template.vibe'));
        $this->assertNull($registry->bridgeForFile('unknown.txt'));
    }

    public function testBridgeRegistryReportsExtensions(): void
    {
        $registry = new BridgeRegistry();
        $registry->register(new JenssBridge());
        $registry->register(new VibeBridge());

        $extensions = $registry->extensions();

        $this->assertContains('jss', $extensions);
        $this->assertContains('vibe', $extensions);
    }

    public function testJenssBridgeReportsMissingDependency(): void
    {
        $bridge = new JenssBridge();
        $context = new BridgeContext();

        $result = $bridge->runSource('define @system as an object;', $context);

        if (class_exists(\BlueFission\Jenerate\Runtime\Interpreter::class)) {
            $this->assertNotNull($result);
            return;
        }

        $this->assertFalse($result->successFlag());
        $this->assertStringContainsString('JenSS interpreter is not available', $result->output());
    }

    public function testVibeBridgeReportsMissingDependency(): void
    {
        $bridge = new VibeBridge();
        $context = new BridgeContext();

        $result = $bridge->runSource('@mod(\"test\") @endmod', $context);

        if (class_exists(\BlueFission\Vibrato\Reader::class)) {
            $this->assertNotNull($result);
            return;
        }

        $this->assertFalse($result->successFlag());
        $this->assertStringContainsString('Vibe interpreter is not available', $result->output());
    }
}
