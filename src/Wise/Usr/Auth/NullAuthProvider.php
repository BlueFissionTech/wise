<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Obj;
use BlueFission\Wise\Usr\Profile;

class NullAuthProvider extends Obj implements AuthProviderInterface
{
    public function available(): bool
    {
        return false;
    }

    public function authenticate(AuthRequest $request): AuthOutcome
    {
        return AuthOutcome::failure('authentication_provider_unavailable');
    }

    public function logout(): void
    {
    }

    public function isAuthenticated(): bool
    {
        return false;
    }

    public function profile(): ?Profile
    {
        return null;
    }

    public function hasPermission(string $permission): bool
    {
        return false;
    }
}
