<?php

namespace BlueFission\Tests;

use BlueFission\Data\Storage\Memory;
use BlueFission\Services\Authenticator;
use BlueFission\Wise\Usr\Identity;
use BlueFission\Wise\Arc\Kernel;
use PHPUnit\Framework\TestCase;

final class IdentityProfileTest extends TestCase
{
    public function testProfileDefaultsToGuest(): void
    {
        $identity = $this->makeIdentity();

        $profile = $identity->profile();

        $this->assertSame('guest', $profile->id());
        $this->assertTrue($profile->hasRole('guest'));
    }

    public function testProfileUsesAuthenticatedSession(): void
    {
        [$identity, $auth, $session] = $this->makeIdentityWithAuth();

        $session->username = 'alex';
        $session->id = 'user-42';
        $session->write();

        $this->assertTrue($auth->isAuthenticated());

        $profile = $identity->profile();

        $this->assertSame('user-42', $profile->id());
        $this->assertTrue($profile->hasRole('user'));
    }

    private function makeIdentity(): Identity
    {
        $session = new Memory();
        $data = new Memory();
        $auth = new Authenticator($session, $data);

        return new Identity(new FakeKernelForIdentity(), $auth);
    }

    private function makeIdentityWithAuth(): array
    {
        $session = new Memory();
        $data = new Memory();
        $auth = new Authenticator($session, $data);
        $identity = new Identity(new FakeKernelForIdentity(), $auth);

        return [$identity, $auth, $session];
    }
}

final class FakeKernelForIdentity extends Kernel
{
    public function __construct()
    {
    }
}
