<?php

namespace BlueFission\Tests;

use BlueFission\Str;
use BlueFission\Wise\Sys\FileSystemManager;
use PHPUnit\Framework\TestCase;

final class ReleaseDistributionTest extends TestCase
{
    public function testReleaseWorkflowPublishesOnlyGithubVcsRelease(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github'
            . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'release.yml';
        $contents = FileSystemManager::readPath($path);

        $this->assertIsString($contents);

        $workflow = Str::lower($contents);

        $this->assertTrue(Str::has($workflow, 'name: publish github vcs prerelease'));
        $this->assertTrue(Str::has($workflow, 'gh release create'));
        $this->assertTrue(Str::has($workflow, 'composer config repositories.wise vcs'));
        $this->assertFalse(Str::has($workflow, 'packagist'));
    }
}
