<?php

namespace BlueFission\Wise\Usr;

use BlueFission\Arr;

class Profile
{
    protected string $_id;
    protected array $_roles;
    protected array $_permissions;

    public function __construct(string $id, array $roles = ['user'], array $permissions = [])
    {
        $this->_id = $id;
        $this->_roles = $roles;
        $this->_permissions = $permissions;
    }

    public function id(): string
    {
        return $this->_id;
    }

    public function roles(): array
    {
        return $this->_roles;
    }

    public function hasRole(string $role): bool
    {
        return Arr::has($this->_roles, $role, true);
    }

    public function permissions(): array
    {
        return $this->_permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return Arr::has($this->_permissions, $permission, true);
    }
}
