<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Cmd\CommandHandler;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Usr\Profile;
use PHPUnit\Framework\TestCase;

final class CommandHandlerTest extends TestCase
{
    public function testCanHandleAliasOrMethod(): void
    {
        $handler = new CommandHandler(new FakeKernel());

        $this->assertTrue($handler->canHandle('list something'));
        $this->assertTrue($handler->canHandle('listDir'));
        $this->assertTrue($handler->canHandle('list all resources'));
        $this->assertFalse($handler->canHandle('unknown'));
    }

    public function testHandleUsesAliasRouting(): void
    {
        $kernel = new FakeKernel();
        $handler = new CommandHandler($kernel);

        $result = $handler->handle('list docs');

        $this->assertSame('list:docs', $result);
        $this->assertSame(['listDir', 'docs'], $kernel->lastCall);
    }

    public function testListAllUsesResourceHelperInsteadOfFilesystem(): void
    {
        $kernel = new FakeKernel();
        $handler = new CommandHandler($kernel);

        $result = $handler->handle('list all');

        $this->assertStringContainsString('List of available resources:', $result);
        $this->assertSame([], $kernel->lastCall);
    }

    public function testHandleEchoReturnsMessage(): void
    {
        $handler = new CommandHandler(new FakeKernel());

        $result = $handler->handle('echo hello');

        $this->assertSame('hello', $result);
    }

    public function testExitReturnsMessageWithoutTerminatingProcess(): void
    {
        $handler = new CommandHandler(new FakeKernel());

        $this->assertSame('Goodbye.', $handler->handle('exit'));
    }

    public function testClearScreenReturnsEscapeSequence(): void
    {
        $handler = new CommandHandler(new FakeKernel());

        $result = $handler->clearScreen();

        $this->assertContains($result, ["\e[H\e[J", "\033[2J\033[;H"]);
    }

    public function testHandleUnknownCommandThrows(): void
    {
        $handler = new CommandHandler(new FakeKernel());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Command not found');

        $handler->handle('unknown');
    }

    public function testMemoryCommandSetsUserMaxSize(): void
    {
        $kernel = new FakeKernel();
        $handler = new CommandHandler($kernel);

        $result = $handler->handle('memory 5');

        $this->assertSame('Working memory user max size set to 5.', $result);
        $this->assertSame(5, $kernel->memoryMax['user']);
    }

    public function testMemoryCommandBlocksGlobalForNonAdmin(): void
    {
        $kernel = new FakeKernel();
        $handler = new CommandHandler($kernel);

        $result = $handler->handle('memory global 5');

        $this->assertSame('Insufficient permissions to modify global memory.', $result);
        $this->assertNull($kernel->memoryMax['global']);
    }
}

final class FakeKernel extends Kernel
{
    public array $lastCall = [];
    public array $memoryMax = ['user' => null, 'global' => null];
    public bool $workingMemoryEnabled = true;
    private Profile $profile;

    public function __construct()
    {
        $this->profile = new Profile('user-1', ['user']);
    }

    public function listDir($dir = null)
    {
        $this->lastCall = ['listDir', $dir];
        return "list:{$dir}";
    }

    public function changeDir($path)
    {
        $this->lastCall = ['changeDir', $path];
        return "cd:{$path}";
    }

    public function createDir($dir)
    {
        $this->lastCall = ['createDir', $dir];
        return "mkdir:{$dir}";
    }

    public function createFile($file)
    {
        $this->lastCall = ['createFile', $file];
        return "touch:{$file}";
    }

    public function writeFile($file, $contents)
    {
        $this->lastCall = ['writeFile', $file, $contents];
        return "write:{$file}";
    }

    public function deleteFile($file)
    {
        $this->lastCall = ['deleteFile', $file];
        return "rm:{$file}";
    }

    public function readFile($file)
    {
        $this->lastCall = ['readFile', $file];
        return "cat:{$file}";
    }

    public function moveFile($destination, $file = null)
    {
        $this->lastCall = ['moveFile', $destination, $file];
        return "mv:{$destination}";
    }

    public function copyFile($destination, $file = null)
    {
        $this->lastCall = ['copyFile', $destination, $file];
        return "cp:{$destination}";
    }

    public function hasWorkingMemory(): bool
    {
        return $this->workingMemoryEnabled;
    }

    public function setWorkingMemoryMaxSize(string $scope, ?int $size, ?string $ownerId = null): void
    {
        $key = $scope === 'global' ? 'global' : 'user';
        $this->memoryMax[$key] = $size;
    }

    public function workingMemoryMaxSize(string $scope, ?string $ownerId = null): ?int
    {
        return $this->memoryMax[$scope] ?? null;
    }

    public function profile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }
}
