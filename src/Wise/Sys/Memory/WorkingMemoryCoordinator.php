<?php

namespace BlueFission\Wise\Sys\Memory;

use BlueFission\Wise\Usr\Profile;

class WorkingMemoryCoordinator
{
    protected object $_reader;
    protected MemoryPolicy $_policy;
    protected IMemoryWorkspace $_global;
    protected array $_users = [];
    protected $partitionFactory = null;
    protected ?int $_defaultGlobalMaxSize = null;
    protected ?int $_defaultUserMaxSize = null;

    public function __construct(object $reader, ?MemoryPolicy $policy = null, ?callable $partitionFactory = null)
    {
        $this->_reader = $reader;
        $this->_policy = $policy ?? new DefaultMemoryPolicy();
        $this->partitionFactory = $partitionFactory;
        $this->_global = $this->makePartition('global', null);
    }

    public function global(): IMemoryWorkspace
    {
        return $this->_global;
    }

    public function user(string $userId): IMemoryWorkspace
    {
        if (!isset($this->_users[$userId])) {
            $partition = $this->makePartition('user', $userId);
            if ($this->_defaultUserMaxSize !== null) {
                $partition->setMaxSize($this->_defaultUserMaxSize);
            }
            $this->_users[$userId] = $partition;
        }

        return $this->_users[$userId];
    }

    public function setPartitionFactory(?callable $factory): void
    {
        $this->partitionFactory = $factory;
    }

    public function setGlobalPartition(IMemoryWorkspace $partition): void
    {
        $this->_global = $partition;
        if ($this->_defaultGlobalMaxSize !== null) {
            $this->_global->setMaxSize($this->_defaultGlobalMaxSize);
        }
    }

    public function setUserPartition(string $userId, IMemoryWorkspace $partition): void
    {
        if ($this->_defaultUserMaxSize !== null) {
            $partition->setMaxSize($this->_defaultUserMaxSize);
        }
        $this->_users[$userId] = $partition;
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

    public function memory(string $scope, Profile $actor, ?string $ownerId = null): ?IMemoryWorkspace
    {
        if (!$this->_policy->canRead($actor, $scope, $ownerId)) {
            return null;
        }

        return $scope === 'global' ? $this->global() : $this->user($ownerId ?? $actor->id());
    }

    protected function makePartition(string $scope, ?string $ownerId): IMemoryWorkspace
    {
        if ($this->partitionFactory) {
            $partition = call_user_func($this->partitionFactory, $this->_reader, $scope, $ownerId);
            if ($partition instanceof IMemoryWorkspace) {
                return $partition;
            }
        }

        return new MemoryPartition($this->_reader);
    }
}
