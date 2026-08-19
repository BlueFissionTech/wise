<?php

namespace BlueFission\Tests\Res;

use BlueFission\Services\Application as App;
use BlueFission\Str;
use BlueFission\Wise\Commands\FileResource as LegacyFileResource;
use BlueFission\Wise\Res\ActionResource;
use BlueFission\Wise\Res\APIResource;
use BlueFission\Wise\Res\FileResource;
use BlueFission\Wise\Res\NoteResource;
use BlueFission\Wise\Res\ScheduleResource;
use BlueFission\Wise\Res\StepResource;
use BlueFission\Wise\Res\TodoResource;
use BlueFission\Wise\Res\VariableResource;
use BlueFission\Wise\Sys\DirectoryManager;
use BlueFission\Wise\Sys\StorageRoot;
use BlueFission\Val;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class ResourceStorageRootTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-resource-storage-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    #[DataProvider('resourceClasses')]
    public function testResourceConstructsWithInjectedStorageRoot(
        string $resourceClass,
        string $scope,
        ?string $storagePropertyName
    ): void
    {
        $resource = new $resourceClass(new StorageRoot($this->root));

        $this->assertInstanceOf($resourceClass, $resource);
        $this->assertTrue(DirectoryManager::pathExists(
            $this->root . DIRECTORY_SEPARATOR . $scope
        ));

        if ($storagePropertyName) {
            $storageProperty = new ReflectionProperty($resourceClass, $storagePropertyName);
            $storage = $storageProperty->getValue($resource);
            $sourceProperty = new ReflectionProperty($storage, '_source');
            $source = $sourceProperty->getValue($storage);
            if (Val::is($source)) {
                $source->close();
                $sourceProperty->setValue($storage, null);
            }
        }
        unset($resource);
        gc_collect_cycles();
    }

    public static function resourceClasses(): array
    {
        return [
            'action' => [ActionResource::class, 'system', '_storage'],
            'api' => [APIResource::class, 'system', '_storage'],
            'file' => [FileResource::class, 'files', null],
            'note' => [NoteResource::class, 'system', '_storage'],
            'schedule' => [ScheduleResource::class, 'system', '_storage'],
            'step' => [StepResource::class, 'system', '_storage'],
            'todo' => [TodoResource::class, 'system', '_storage'],
            'variable' => [VariableResource::class, 'system', '_storage'],
        ];
    }

    public function testCanonicalFileResourceLoadsFromItsPsr4Path(): void
    {
        $reflection = new ReflectionClass(FileResource::class);
        $path = Str::make((string)$reflection->getFileName())
            ->replace('\\', '/')
            ->val();

        $this->assertSame(FileResource::class, $reflection->getName());
        $this->assertStringEndsWith('/src/Wise/Res/FileResource.php', $path);
    }

    public function testLegacyFileResourceUsesItsOwnCompatibilityPath(): void
    {
        $reflection = new ReflectionClass(LegacyFileResource::class);
        $path = Str::make((string)$reflection->getFileName())
            ->replace('\\', '/')
            ->val();

        $this->assertTrue($reflection->isSubclassOf(FileResource::class));
        $this->assertStringEndsWith('/src/Wise/Commands/FileResource.php', $path);
    }

    public function testCanonicalFileResourceResolvesThroughApplicationDelegation(): void
    {
        $serviceName = Str::make('file-resource-')
            ->append(Str::make()->rand()->val())
            ->val();
        $storageRoot = new StorageRoot($this->root);

        App::instance()->delegate($serviceName, FileResource::class, [$storageRoot]);
        $resource = App::instance()->service($serviceName);

        $this->assertInstanceOf(FileResource::class, $resource);
        $this->assertTrue(DirectoryManager::pathExists(
            $this->root . DIRECTORY_SEPARATOR . 'files'
        ));
    }

    private function removeDirectory(string $path): void
    {
        if (!DirectoryManager::pathExists($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (DirectoryManager::pathExists($target)) {
                $this->removeDirectory($target);
                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
