<?php

namespace BlueFission\Wise;

use Composer\InstalledVersions;

final class Version
{
    public const CURRENT = '0.1.0-alpha.1';

    private function __construct()
    {
    }

    public static function package(string $package): string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return 'unavailable';
        }

        return InstalledVersions::getPrettyVersion($package)
            ?? InstalledVersions::getVersion($package)
            ?? 'unknown';
    }
}
