<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Wise\Usr\Profile;

class WorkingMemoryCoordinator
{
    protected object $_reader;
    protected MemoryPolicy $_policy;
    protected MemoryPartition $_global;
    protected array $_users = [];
    protected ?int $_defaultGlobalMaxSize = null;
    protected ?int $_defaultUserMaxSize = null;

    public function __construct(object $reader, ?MemoryPolicy $policy = null)
    {
        $this->_reader = $reader;
        $this->_policy = $policy ?? new DefaultMemoryPolicy();
        $this->_global = new MemoryPartition($reader);
    }

    public function global(): MemoryPartition
    {
        return $this->_global;
    }

    public function user(string $userId): MemoryPartition
    {
        if (!isset($this->_users[$userId])) {
            $partition = new MemoryPartition($this->_reader);
            if ($this->_defaultUserMaxSize !== null) {
                $partition->setMaxSize($this->_defaultUserMaxSize);
            }
            $this->_users[$userId] = $partition;
        }

        return $this->_users[$userId];
    }

    public function setGlobalMaxSize(?int $maxSize): void
    {
        $this->_defaultGlobalMaxSize = $maxSize;
        $this->_global->setMaxSize($maxSize);
    }

    public function setUserMaxSize(?int $maxSize): void
    {
        $this->_defaultUserMaxSize = $maxSize;
        foreach ($this->_users as $partition) {
            $partition->setMaxSize($maxSize);
        }
    }

    public function setPartitionMaxSize(string $scope, ?int $maxSize, ?string $ownerId = null): void
    {
        if ($scope === 'global') {
            $this->setGlobalMaxSize($maxSize);
            return;
        }

        $id = $ownerId ?? '';
        if ($id === '') {
            return;
        }
        $this->user($id)->setMaxSize($maxSize);
    }

    public function partitionMaxSize(string $scope, ?string $ownerId = null): ?int
    {
        if ($scope === 'global') {
            return $this->_global->maxSize();
        }

        $id = $ownerId ?? '';
        if ($id === '') {
            return null;
        }

        return $this->user($id)->maxSize();
    }

    public function record(string $scope, string $text, string $episodeId, Profile $actor, ?string $ownerId = null): bool
    {
        if (!$this->_policy->canWrite($actor, $scope, $ownerId)) {
            return false;
        }

        $partition = $scope === 'global' ? $this->global() : $this->user($ownerId ?? $actor->id());
        $partition->record($text, $episodeId);

        return true;
    }

    public function memory(string $scope, Profile $actor, ?string $ownerId = null): ?MemoryPartition
    {
        if (!$this->_policy->canRead($actor, $scope, $ownerId)) {
            return null;
        }

        return $scope === 'global' ? $this->global() : $this->user($ownerId ?? $actor->id());
    }
}
