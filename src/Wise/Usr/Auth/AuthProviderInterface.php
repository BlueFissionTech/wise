<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Wise\Usr\Profile;

interface AuthProviderInterface
{
    public function available(): bool;

    public function authenticate(AuthRequest $request): AuthOutcome;

    public function logout(): void;

    public function isAuthenticated(): bool;

    public function profile(): ?Profile;

    public function hasPermission(string $permission): bool;
}
