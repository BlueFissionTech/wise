<?php

namespace BlueFission\Tests;

use BlueFission\Data\Storage\Memory;
use BlueFission\Services\Authenticator;
use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Usr\Auth\AuthRequest;
use BlueFission\Wise\Usr\Auth\NullAuthProvider;
use BlueFission\Wise\Usr\Auth\PresenceAuthProvider;
use BlueFission\Wise\Usr\Identity;
use PHPUnit\Framework\TestCase;

final class AuthProviderTest extends TestCase
{
    public function testNullProviderFailsDeterministically(): void
    {
        $provider = new NullAuthProvider();

        $outcome = $provider->authenticate(new AuthRequest('alex', 'secret'));

        $this->assertFalse($provider->available());
        $this->assertFalse($outcome->authenticated());
        $this->assertSame('authentication_provider_unavailable', $outcome->reason());
        $this->assertNull($outcome->profile());
    }

    public function testPresenceProviderMapsPrincipalRolesAndPermissions(): void
    {
        $registry = new FakePresenceRegistry(FakePresenceResult::success(
            new FakePresencePrincipal(
                'user-42',
                new FakePresenceCollection(['operator', 'reviewer']),
                new FakePresenceCollection(['resource.read', 'resource.execute'])
            ),
            ['authenticator' => 'password']
        ));
        $provider = $this->makeProvider($registry);

        $outcome = $provider->authenticate(new AuthRequest(
            'alex',
            'not-exported',
            context: ['session_id' => 'session-1', 'tenant_id' => 'tenant-1']
        ));

        $this->assertTrue($provider->available());
        $this->assertTrue($outcome->authenticated());
        $this->assertSame('user-42', $outcome->profile()?->id());
        $this->assertTrue($outcome->profile()?->hasRole('operator'));
        $this->assertTrue($provider->hasPermission('resource.execute'));
        $this->assertSame('presence', $outcome->metadata()['provider']);
        $this->assertArrayNotHasKey('secret', $outcome->metadata());
        $this->assertSame('not-exported', $registry->credential?->secret);
        $this->assertSame('session-1', $registry->context?->sessionId);
    }

    public function testPresenceProviderMapsFailureWithoutPrincipal(): void
    {
        $provider = $this->makeProvider(new FakePresenceRegistry(
            FakePresenceResult::failure('credential_rejected', ['attempts_remaining' => 2])
        ));

        $outcome = $provider->authenticate(new AuthRequest('alex', 'invalid'));

        $this->assertFalse($outcome->authenticated());
        $this->assertSame('credential_rejected', $outcome->reason());
        $this->assertSame(2, $outcome->metadata()['attempts_remaining']);
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testPresenceProviderNormalizesExceptions(): void
    {
        $provider = $this->makeProvider(new FakePresenceRegistry(exception: new \RuntimeException('offline')));

        $outcome = $provider->authenticate(new AuthRequest('alex', 'secret'));

        $this->assertFalse($outcome->authenticated());
        $this->assertSame('presence_authentication_failed', $outcome->reason());
        $this->assertSame(\RuntimeException::class, $outcome->metadata()['exception']);
        $this->assertArrayNotHasKey('secret', $outcome->metadata());
    }

    public function testIdentityUsesConfiguredProviderAndReturnsToGuestAfterLogout(): void
    {
        $provider = $this->makeProvider(new FakePresenceRegistry(FakePresenceResult::success(
            new FakePresencePrincipal(
                'user-42',
                new FakePresenceCollection(['operator']),
                new FakePresenceCollection(['resource.read'])
            )
        )));
        $identity = new Identity(
            new FakeKernelForAuthProvider(),
            new Authenticator(new Memory(), new Memory()),
            $provider
        );

        $identity->authenticate('alex', 'secret');

        $this->assertTrue($identity->isAuthenticated());
        $this->assertSame('user-42', $identity->profile()->id());
        $this->assertTrue($identity->profile()->hasPermission('resource.read'));

        $identity->logout();

        $this->assertFalse($identity->isAuthenticated());
        $this->assertSame('guest', $identity->profile()->id());
    }

    private function makeProvider(FakePresenceRegistry $registry): PresenceAuthProvider
    {
        return new PresenceAuthProvider(
            $registry,
            static fn (AuthRequest $request): object => (object)[
                'type' => $request->type(),
                'identifier' => $request->identifier(),
                'secret' => $request->secret(),
                'metadata' => $request->context(),
            ],
            static fn (AuthRequest $request): object => (object)[
                'sessionId' => $request->context()['session_id'] ?? '',
                'tenantId' => $request->context()['tenant_id'] ?? '',
                'metadata' => $request->context(),
            ]
        );
    }
}

final class FakeKernelForAuthProvider extends Kernel
{
    public function __construct()
    {
    }
}

final class FakePresenceRegistry
{
    public ?object $credential = null;
    public ?object $context = null;

    public function __construct(
        private ?FakePresenceResult $result = null,
        private ?\Throwable $exception = null
    ) {
    }

    public function authenticate(object $credential, object $context): FakePresenceResult
    {
        $this->credential = $credential;
        $this->context = $context;

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->result ?? FakePresenceResult::failure('authentication_failed');
    }
}

final class FakePresenceResult
{
    public function __construct(
        private bool $successful,
        public ?FakePresencePrincipal $principal,
        public string $reason,
        public array $metadata = []
    ) {
    }

    public static function success(FakePresencePrincipal $principal, array $metadata = []): self
    {
        return new self(true, $principal, 'authenticated', $metadata);
    }

    public static function failure(string $reason, array $metadata = []): self
    {
        return new self(false, null, $reason, $metadata);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }
}

final class FakePresencePrincipal
{
    public function __construct(
        private string $id,
        private FakePresenceCollection $roles,
        private FakePresenceCollection $permissions
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function roles(): FakePresenceCollection
    {
        return $this->roles;
    }

    public function permissions(): FakePresenceCollection
    {
        return $this->permissions;
    }
}

final class FakePresenceCollection
{
    public function __construct(private array $names)
    {
    }

    public function toArray(bool $allowEmpty = false): array
    {
        return array_map(
            static fn (string $name): object => (object)['name' => $name],
            $this->names
        );
    }
}
