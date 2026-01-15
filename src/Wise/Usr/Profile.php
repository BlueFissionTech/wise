<?php

namespace BlueFission\Wise\Usr;

class Profile
{
    protected string $_id;
    protected array $_roles;

    public function __construct(string $id, array $roles = ['user'])
    {
        $this->_id = $id;
        $this->_roles = $roles;
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
        return in_array($role, $this->_roles, true);
    }
}
