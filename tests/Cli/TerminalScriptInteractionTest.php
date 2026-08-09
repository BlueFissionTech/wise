<?php

namespace BlueFission\Tests\Cli;

use BlueFission\Wise\Sys\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class TerminalScriptInteractionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-cli-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        $defaultStorage = $this->projectRoot()
            . DIRECTORY_SEPARATOR . 'artifacts'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'wise_cli_storage.json';
        if (DirectoryManager::pathExists($defaultStorage)) {
            @unlink($defaultStorage);
        }
    }

    public function testBatchCliProcessesInputFileAndPrintsDeterministicOutput(): void
    {
        $inputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'commands.txt';
        file_put_contents($inputFile, "help\nlist all resources\nexit\n");

        $result = $this->runCli([
            '--input-file', $inputFile,
            '--display-mode', 'static',
            '--output-mode', 'console',
            '--input-exit',
        ]);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $this->assertStringContainsString('Available commands:', $result['stdout']);
        $this->assertStringContainsString('List of available resources:', $result['stdout']);
        $this->assertDoesNotMatchRegularExpression('/\x1b\[[0-9;?]*[A-Za-z]/', $result['stdout']);
    }

    public function testBatchCliUsesWiseStorageDefaultsForResourceHelper(): void
    {
        $inputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'commands.txt';
        file_put_contents($inputFile, "list all\nexit\n");

        $result = $this->runCli([
            '--input-file', $inputFile,
            '--display-mode', 'static',
            '--output-mode', 'console',
            '--input-exit',
        ], false);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $this->assertStringContainsString('List of available resources:', $result['stdout']);
    }

    /**
     * @param array<int, string> $args
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runCli(array $args, bool $injectStorageEnv = true): array
    {
        $cmd = array_merge([PHP_BINARY, $this->projectRoot() . DIRECTORY_SEPARATOR . 'terminal.php'], $args);
        $command = implode(' ', array_map('escapeshellarg', $cmd));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $_ENV;
        $env['WISE_NAV_BOOT'] = '0';
        $env['WISE_DISPLAY_MODE'] = 'static';
        $env['WISE_INPUT_EXIT'] = '1';
        $env['WISE_TTY_ECHO'] = '0';
        if ($injectStorageEnv) {
            $env['STORAGE_PATH'] = $this->tempDir;
            $env['STORAGE_FILE_NAME'] = 'wise_cli_test_storage.json';
        } else {
            unset($env['STORAGE_PATH'], $env['STORAGE_FILE_NAME']);
        }
        $env['CLI_SESSION_ID'] = 'wise-cli-test';

        $process = proc_open($command, $descriptors, $pipes, $this->projectRoot(), $env);
        if (!is_resource($process)) {
            $this->fail('Failed to start Wise CLI process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + 15;
        $timedOut = false;
        $exitCode = -1;

        while (true) {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }

            usleep(50000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeExitCode = proc_close($process);
        if ($exitCode < 0) {
            $exitCode = $closeExitCode;
        }

        if ($timedOut) {
            $this->fail('Wise CLI process timed out. stdout: ' . $stdout . ' stderr: ' . $stderr);
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function removeDir(string $dir): void
    {
        if (!DirectoryManager::pathExists($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
