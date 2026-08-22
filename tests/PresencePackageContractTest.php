<?php

namespace BlueFission\Tests;

use BlueFission\Presence\Auth\AuthResult;
use BlueFission\Presence\Auth\AuthenticatorRegistry;
use BlueFission\Presence\Auth\Credential;
use BlueFission\Presence\Auth\CredentialTypeAuthenticator;
use BlueFission\Presence\Auth\Principal;
use BlueFission\Presence\Policy\Permission;
use BlueFission\Presence\Policy\Role;
use BlueFission\Presence\Support\Context;
use BlueFission\Wise\Usr\Auth\AuthRequest;
use BlueFission\Wise\Usr\Auth\PresenceAuthProvider;
use PHPUnit\Framework\TestCase;

final class PresencePackageContractTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AuthenticatorRegistry::class)) {
            $this->markTestSkipped('Presence package is not installed.');
        }
    }

    public function testReleasedRegistryMapsPrincipalRolesAndPermissions(): void
    {
        $registry = new AuthenticatorRegistry();
        $registry->register(new CredentialTypeAuthenticator(
            'password',
            static function (Credential $credential, Context $context): AuthResult {
                $principal = new Principal();
                $principal->id = 'presence-user';

                $role = new Role();
                $role->name = 'operator';
                $principal->roles()->add($role);

                $permission = new Permission();
                $permission->name = 'resource.execute';
                $principal->permissions()->add($permission);

                return AuthResult::success($principal, $credential, [
                    'session_id' => (string)$context->session_id,
                    'tenant_id' => (string)$context->tenant_id,
                ]);
            }
        ));

        $provider = new PresenceAuthProvider($registry);
        $outcome = $provider->authenticate(new AuthRequest(
            'alex',
            'not-exported',
            context: ['session_id' => 'session-1', 'tenant_id' => 'tenant-1']
        ));

        $this->assertTrue($outcome->authenticated());
        $this->assertSame('presence-user', $outcome->profile()?->id());
        $this->assertTrue($outcome->profile()?->hasRole('operator'));
        $this->assertTrue($outcome->profile()?->hasPermission('resource.execute'));
        $this->assertSame('session-1', $outcome->metadata()['session_id']);
        $this->assertSame('tenant-1', $outcome->metadata()['tenant_id']);
        $this->assertArrayNotHasKey('secret', $outcome->metadata());
    }

    public function testReleasedRegistryReportsUnsupportedCredentialsWithoutThrowing(): void
    {
        $registry = new AuthenticatorRegistry();
        $registry->register(new CredentialTypeAuthenticator('token', static fn (): bool => true));
        $provider = new PresenceAuthProvider($registry);

        $outcome = $provider->authenticate(new AuthRequest('alex', 'invalid', 'password'));

        $this->assertFalse($outcome->authenticated());
        $this->assertSame('unsupported_credential', $outcome->reason());
        $this->assertSame(1, $outcome->metadata()['registered_authenticators']);
    }
}
