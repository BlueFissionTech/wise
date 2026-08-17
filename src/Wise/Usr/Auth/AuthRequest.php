<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

class AuthRequest extends Obj
{
    private Str $type;
    private Str $identifier;
    private Str $secret;
    private Arr $context;

    public function __construct(string $identifier, string $secret, string $type = 'password', array $context = [])
    {
        parent::__construct();
        $this->type = Str::make($type)->trim()->lower();
        $this->identifier = Str::make($identifier)->trim();
        $this->secret = Str::make($secret);
        $this->context = Arr::make($context);
    }

    public function type(): string
    {
        return $this->type->val();
    }

    public function identifier(): string
    {
        return $this->identifier->val();
    }

    public function secret(): string
    {
        return $this->secret->val();
    }

    public function context(): array
    {
        return $this->context->toArray();
    }
}
