<?php

namespace BlueFission\Wise\Int;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Usr\Profile;

class PersonaContext extends Obj
{
    private Str $id;
    private Arr $roles;
    private Arr $permissions;
    private Arr $attributes;

    public function __construct(string $id, array $roles = [], array $permissions = [], array $attributes = [])
    {
        parent::__construct();
        $this->id = Str::make($id)->trim();
        $this->roles = Arr::make($this->normalizeNames($roles));
        $this->permissions = Arr::make($this->normalizeNames($permissions));
        $this->attributes = Arr::make($attributes);
    }

    public static function fromProfile(Profile $profile, array $attributes = []): self
    {
        $permissions = method_exists($profile, 'permissions') ? $profile->permissions() : [];

        return new self($profile->id(), $profile->roles(), $permissions, $attributes);
    }

    public function id(): string
    {
        return $this->id->val();
    }

    public function roles(): array
    {
        return $this->roles->toArray();
    }

    public function permissions(): array
    {
        return $this->permissions->toArray();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'roles' => $this->roles(),
            'permissions' => $this->permissions(),
            'attributes' => $this->attributes->toArray(),
        ];
    }

    private function normalizeNames(array $values): array
    {
        $names = [];
        foreach ($values as $value) {
            $name = Str::make((string)$value)->trim()->lower()->val();
            if (Str::isNotEmpty($name)) {
                $names[] = $name;
            }
        }

        return Arr::make($names)->unique()->values()->toArray();
    }
}
