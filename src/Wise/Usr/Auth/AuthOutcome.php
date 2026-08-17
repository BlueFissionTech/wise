<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Wise\Usr\Profile;

class AuthOutcome extends Obj
{
    private bool $authenticated;
    private ?Profile $profile;
    private Str $reason;
    private Arr $metadata;

    public function __construct(
        bool $authenticated,
        ?Profile $profile = null,
        string $reason = '',
        array $metadata = []
    ) {
        parent::__construct();
        $this->authenticated = $authenticated;
        $this->profile = $profile;
        $this->reason = Str::make($reason);
        $this->metadata = Arr::make($metadata);
    }

    public static function success(Profile $profile, array $metadata = []): self
    {
        return new self(true, $profile, 'authenticated', $metadata);
    }

    public static function failure(string $reason, array $metadata = []): self
    {
        return new self(false, null, $reason, $metadata);
    }

    public function authenticated(): bool
    {
        return $this->authenticated;
    }

    public function profile(): ?Profile
    {
        return $this->profile;
    }

    public function reason(): string
    {
        return $this->reason->val();
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }
}
