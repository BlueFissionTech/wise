<?php

namespace BlueFission\Tests;

use BlueFission\DevElation as Dev;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeMiddleware;
use BlueFission\Presence\Bridge\BridgeReason;
use BlueFission\Presence\Session\Session;
use BlueFission\Presence\Support\EventNames;
use BlueFission\Wise\Cmd\CommandRequest;
use BlueFission\Wise\Usr\Auth\AuthOutcome;
use BlueFission\Wise\Usr\Auth\PresenceIdentityBridge;
use BlueFission\Wise\Usr\Profile;
use PHPUnit\Framework\TestCase;

final class PresenceIdentityBridgeTest extends TestCase
{
    protected function tearDown(): void
    {
        Dev::down();
    }

    public function testBindsAuthenticatedProfileToCanonicalPresenceContracts(): void
    {
        $outcome = AuthOutcome::success(
            new Profile('user-42', ['operator'], ['resource.execute']),
            [
                'principal_type' => 'human',
                'session_id' => 'session-1',
                'tenant_id' => 'tenant-1',
                'secret' => 'not-exported',
            ]
        );
        $session = $this->session();
        $bridge = new PresenceIdentityBridge($outcome, $session, [
            'strictness_level' => 2,
            'compliance' => ['internal'],
            'scopes' => ['terminal'],
        ]);

        $result = (new BridgeMiddleware($bridge))->process($this->context());

        $this->assertTrue($result->isBound());
        $this->assertSame(BridgeReason::BOUND, $result->reasonCode());
        $this->assertSame('user-42', $result->principal()['id']);
        $this->assertSame('human', $result->principal()['type']);
        $this->assertContains('operator', $result->principal()['roles']);
        $this->assertContains('resource.execute', $result->principal()['permissions']);
        $this->assertSame('session-1', $result->sessionMetadata()['id']);
        $this->assertSame('user-42', $result->sessionMetadata()['participant_id']);
        $this->assertTrue($session->participants()->has('user-42'));
        $this->assertSame(2, $result->metadata()['trust_contract']['strictness_level']);
        $this->assertSame(['terminal'], $result->metadata()['trust_contract']['scopes']);
        $this->assertArrayNotHasKey('secret', $result->metadata());
    }

    public function testDeniesUnauthenticatedIdentityWithoutMutatingHostRequest(): void
    {
        $continuation = CommandRequest::resume('continue-1', false, ['correlation_id' => 'corr-1']);
        $result = (new PresenceIdentityBridge(AuthOutcome::failure('credential_rejected')))
            ->bind($this->context());

        $this->assertFalse($result->isBound());
        $this->assertSame(BridgeReason::UNAUTHORIZED, $result->reasonCode());
        $this->assertSame('credential_rejected', $result->metadata()['wise_reason']);
        $this->assertTrue($continuation->isContinuation());
        $this->assertFalse($continuation->approved());
        $this->assertSame('continue-1', $continuation->continuationToken());
    }

    public function testFailsClosedForMissingOrConflictingSessionIdentity(): void
    {
        $outcome = AuthOutcome::success(new Profile('user-42'));
        $missingResult = (new PresenceIdentityBridge($outcome))->bind(
            $this->context(sessionId: '')
        );

        $this->assertFalse($missingResult->isBound());
        $this->assertSame(BridgeReason::INVALID, $missingResult->reasonCode());
        $this->assertSame('identity_session_missing', $missingResult->metadata()['wise_reason']);

        $session = $this->session('session-existing');
        $conflictingResult = (new PresenceIdentityBridge($outcome, $session))->bind(
            $this->context(sessionId: 'session-other')
        );

        $this->assertFalse($conflictingResult->isBound());
        $this->assertSame(BridgeReason::INVALID, $conflictingResult->reasonCode());
        $this->assertSame('identity_session_missing', $conflictingResult->metadata()['wise_reason']);
        $this->assertCount(0, $session->participants()->toArray());
    }

    public function testRepeatedBindingDoesNotDuplicateSessionParticipant(): void
    {
        $session = $this->session();
        $bridge = new PresenceIdentityBridge(
            AuthOutcome::success(new Profile('user-42')),
            $session
        );
        $context = $this->context();

        $first = $bridge->bind($context);
        $second = $bridge->bind($context);

        $this->assertTrue($first->isBound());
        $this->assertTrue($second->isBound());
        $this->assertCount(1, $session->participants()->toArray());
    }

    public function testRejectsTenantMismatchWithPackageOwnedReason(): void
    {
        $result = (new PresenceIdentityBridge(AuthOutcome::success(
            new Profile('user-42'),
            ['tenant_id' => 'tenant-other']
        )))->bind($this->context());

        $this->assertFalse($result->isBound());
        $this->assertSame(BridgeReason::TENANT_MISMATCH, $result->reasonCode());
        $this->assertSame('identity_tenant_mismatch', $result->metadata()['wise_reason']);
    }

    public function testDispatchesLifecycleHooksAndPresenceEvents(): void
    {
        $actions = [];
        $events = [];
        Dev::action(PresenceIdentityBridge::BEFORE, function () use (&$actions): void {
            $actions[] = 'before';
        });
        Dev::action(PresenceIdentityBridge::AFTER, function () use (&$actions): void {
            $actions[] = 'after';
        });
        Dev::listen(EventNames::BRIDGE_BOUND);
        Dev::subscribe(function () use (&$events): void {
            $events[] = 'bound';
        }, EventNames::BRIDGE_BOUND);
        Dev::up();

        $result = (new PresenceIdentityBridge(
            AuthOutcome::success(new Profile('user-42')),
            $this->session()
        ))->bind($this->context());

        $this->assertTrue($result->isBound());
        $this->assertSame(['before', 'after'], $actions);
        $this->assertSame(['bound'], $events);
    }

    private function context(string $sessionId = 'session-1'): BridgeContext
    {
        return new BridgeContext(
            host: 'wise',
            tenantId: 'tenant-1',
            applicationId: 'terminal',
            actorId: 'user-42',
            sessionId: $sessionId,
            correlationId: 'corr-1',
            requestedPermissions: ['resource.execute'],
            authenticationRevision: 'auth-1',
            sessionRevision: 'session-revision-1',
            metadata: [
                'participant_id' => 'user-42',
                'session_type' => 'terminal',
            ]
        );
    }

    private function session(string $id = 'session-1'): Session
    {
        $session = new Session('terminal');
        $session->id = $id;

        return $session;
    }
}
