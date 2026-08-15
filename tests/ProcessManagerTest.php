<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Arc\Process;
use BlueFission\Wise\Arc\ProcessManager;
use PHPUnit\Framework\TestCase;

final class ProcessManagerTest extends TestCase
{
    public function testProcessCanBeCreatedBeforeKernelBoot(): void
    {
        $manager = new ProcessManager();

        $process = $manager->createProcess(new \stdClass(), 'inspect');

        $this->assertInstanceOf(Process::class, $process);
    }
}
