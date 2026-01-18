<?php

namespace BlueFission\Tests\Sys;

use PHPUnit\Framework\TestCase;

final class StdioScriptTest extends TestCase
{
    public function testStdinScriptEchoesInputWithoutDebugNoise(): void
    {
        $script = $this->projectRoot() . DIRECTORY_SEPARATOR . 'stdin.php';
        $output = $this->runScript($script, 'x', ['WISE_STDIN_TEST' => '1']);

        $this->assertSame('x', $output);
    }

    public function testPollingScriptEmitsOutputInTestMode(): void
    {
        $script = $this->projectRoot() . DIRECTORY_SEPARATOR . 'polling.php';
        $output = $this->runScript($script, '', ['WISE_POLLING_TEST' => '1']);

        $this->assertStringContainsString('Background process output', $output);
    }

    private function runScript(string $script, string $input = '', array $env = []): string
    {
        $cmd = PHP_BINARY . ' ' . escapeshellarg($script);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->projectRoot(), $env + $_ENV);
        if (!is_resource($process)) {
            $this->fail('Failed to start script process.');
        }

        if ($input !== '') {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        if ($stderr !== '') {
            $this->fail('Script stderr output: ' . $stderr);
        }

        return $stdout;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
