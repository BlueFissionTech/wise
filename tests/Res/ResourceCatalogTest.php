<?php

namespace BlueFission\Tests\Res;

use BlueFission\Wise\Res\CommandResource;
use BlueFission\Wise\Res\ResourceCatalog;
use BlueFission\Wise\Res\SystemResource;
use BlueFission\Wise\Res\WebBrowserResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ResourceCatalogTest extends TestCase
{
    public function testCanonicalDefinitionsReferenceLoadablePsr4Classes(): void
    {
        $catalog = new ResourceCatalog();

        $this->assertSame([], $catalog->missingClasses());
        foreach ($catalog->definitions() as $class) {
            $reflection = new ReflectionClass($class);
            $path = str_replace('\\', '/', (string)$reflection->getFileName());

            $this->assertStringEndsWith(
                '/src/Wise/Res/' . $reflection->getShortName() . '.php',
                $path
            );
        }
    }

    public function testCoreResourcesUseCanonicalPackageClasses(): void
    {
        $catalog = new ResourceCatalog();

        $this->assertSame(SystemResource::class, $catalog->classFor('system'));
        $this->assertSame(CommandResource::class, $catalog->classFor('command'));
        $this->assertSame(WebBrowserResource::class, $catalog->classFor('website'));
        $this->assertSame(WebBrowserResource::class, $catalog->classFor('web'));
    }

    public function testAbsentCapabilitiesAreExplicitlyUnavailable(): void
    {
        $catalog = new ResourceCatalog();

        foreach (['message', 'resource', 'transcript'] as $identifier) {
            $description = $catalog->describe($identifier);

            $this->assertSame(ResourceCatalog::UNAVAILABLE, $description['status']);
            $this->assertSame('not_implemented', $description['reason']);
            $this->assertNull($description['class']);
        }

        $this->assertSame(ResourceCatalog::UNKNOWN, $catalog->status('not-a-resource'));
    }

    public function testCatalogCanBeExtendedWithoutChangingCanonicalDefaults(): void
    {
        $catalog = new ResourceCatalog(
            ['custom' => TestResource::class],
            ['custom-alias' => 'custom']
        );

        $this->assertSame(TestResource::class, $catalog->classFor('custom'));
        $this->assertSame(TestResource::class, $catalog->classFor('custom alias'));
        $this->assertSame(SystemResource::class, $catalog->classFor('system'));
    }
}

final class TestResource
{
}
