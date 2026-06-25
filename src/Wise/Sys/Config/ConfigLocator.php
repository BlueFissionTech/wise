<?php

namespace BlueFission\Wise\Sys\Config;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Sys\FileSystemManager;

class ConfigLocator extends Obj
{
    private string $rootPath;
    private string $configDir = 'cfg';
    private string $userDir = 'usr';
    private ?string $profileId;

    public function __construct(string $rootPath, ?string $profileId = null)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->profileId = $profileId;
    }

    public function setProfileId(?string $profileId): void
    {
        $this->profileId = $profileId;
        $this->dispatch(Event::CHANGE, new Meta(data: ['profile' => $profileId], src: $this));
    }

    public function profileId(): ?string
    {
        return $this->profileId;
    }

    public function systemConfigPath(string $category, string $filename = 'default.jss'): ?string
    {
        $category = $this->normalizeSegment($category);
        $filename = $this->normalizeSegment($filename);

        if (!$category || !$filename) {
            return null;
        }

        return implode(DIRECTORY_SEPARATOR, [
            $this->rootPath,
            $this->configDir,
            $category,
            $filename,
        ]);
    }

    public function userConfigPath(string $category, string $filename = 'default.jss', ?string $profileId = null): ?string
    {
        $profile = $profileId ?? $this->profileId;
        $profile = $this->normalizeSegment($profile);
        $category = $this->normalizeSegment($category);
        $filename = $this->normalizeSegment($filename);

        if (!$profile || !$category || !$filename) {
            return null;
        }

        return implode(DIRECTORY_SEPARATOR, [
            $this->rootPath,
            $this->userDir,
            $profile,
            $this->configDir,
            $category,
            $filename,
        ]);
    }

    public function resolve(string $category, string $filename = 'default.jss', ?string $profileId = null): ?string
    {
        $userPath = $this->userConfigPath($category, $filename, $profileId);
        if ($userPath && FileSystemManager::pathExists($userPath)) {
            $this->dispatch(Event::SUCCESS, new Meta(data: [
                'scope' => 'user',
                'category' => $category,
                'path' => $userPath,
            ], src: $this));
            return $userPath;
        }

        $systemPath = $this->systemConfigPath($category, $filename);
        if ($systemPath && FileSystemManager::pathExists($systemPath)) {
            $this->dispatch(Event::SUCCESS, new Meta(data: [
                'scope' => 'system',
                'category' => $category,
                'path' => $systemPath,
            ], src: $this));
            return $systemPath;
        }

        $this->dispatch(Event::FAILURE, new Meta(data: [
            'category' => $category,
            'filename' => $filename,
        ], src: $this));
        return null;
    }

    public function overlayPaths(string $category, string $filename = 'default.jss', ?string $profileId = null): array
    {
        $paths = [];
        $userPath = $this->userConfigPath($category, $filename, $profileId);
        if ($userPath && FileSystemManager::pathExists($userPath)) {
            $paths[] = $userPath;
        }

        $systemPath = $this->systemConfigPath($category, $filename);
        if ($systemPath && FileSystemManager::pathExists($systemPath)) {
            $paths[] = $systemPath;
        }

        return $paths;
    }

    private function normalizeSegment(?string $value): ?string
    {
        if (Val::isNull($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = trim($value, "\\/");
        if (Str::pos($value, '..') !== false) {
            return null;
        }

        return $value;
    }
}
