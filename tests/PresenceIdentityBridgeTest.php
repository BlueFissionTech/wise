<?php

namespace BlueFission\Tests;

use BlueFission\DevElation as Dev;
use BlueFission\Presence\Auth\AuthResult;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeMiddleware;
use BlueFission\Presence\Session\Session;
use BlueFission\Presence\Support\Context;
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
        $context = $this->context(AuthOutcome::success(
            new Profile('user-42', ['operator'], ['resource.execute']),
            [
                'principal_type' => 'human',
                'session_id' => 'session-1',
                'secret' => 'not-exported',
            ]
        ));
        $context->annex_manifest = [
            'strictness_level' => 2,
            'compliance' => ['internal'],
            'scopes' => ['terminal'],
        ];

        $result = (new BridgeMiddleware(new PresenceIdentityBridge()))->process($context);

        $this->assertTrue($result->isBound());
        $this->assertInstanceOf(AuthResult::class, $result->auth_result);
        $this->assertTrue($result->auth_result->isSuccessful());
        $this->assertSame('user-42', $result->auth_result->principal->id());
        $this->assertSame('human', $result->auth_result->principal->type());
        $this->assertTrue($result->auth_result->principal->roles()->has('operator'));
        $this->assertTrue($result->auth_result->principal->permissions()->has('resource.execute'));
        $this->assertInstanceOf(Session::class, $result->session);
        $this->assertSame('session-1', $result->session->id());
        $this->assertTrue($result->session->participants()->has('user-42'));
        $this->assertSame(2, $result->trust_contract->strictness());
        $this->assertSame($result->session, $result->context->session);
        $this->assertArrayNotHasKey('secret', $result->metadata);
    }

    public function testDeniesUnauthenticatedIdentityWithoutMutatingHostRequest(): void
    {
        $continuation = CommandRequest::resume('continue-1', false, ['correlation_id' => 'corr-1']);
        $context = $this->context(AuthOutcome::failure('credential_rejected'));
        $context->request = $continuation;

        $result = (new PresenceIdentityBridge())->bind($context);

        $this->assertFalse($result->isBound());
        $this->assertSame('credential_rejected', (string)$result->reason);
        $this->assertFalse($result->auth_result->isSuccessful());
        $this->assertSame($continuation, $context->request);
        $this->assertTrue($context->request->isContinuation());
        $this->assertFalse($context->request->approved());
    }

    public function testFailsClosedForMissingOrConflictingSessionIdentity(): void
    {
        $outcome = AuthOutcome::success(new Profile('user-42'));
        $missing = new BridgeContext();
        $missing->host = 'wise';
        $missing->authenticator = $outcome;

        $missingResult = (new PresenceIdentityBridge())->bind($missing);

        $this->assertFalse($missingResult->isBound());
        $this->assertSame('identity_session_missing', (string)$missingResult->reason);

        $session = new Session('terminal');
        $session->id = 'session-existing';
        $conflicting = $this->context($outcome);
        $conflicting->session = $session;
        $conflicting->context()->session_id = 'session-other';

        $conflictingResult = (new PresenceIdentityBridge())->bind($conflicting);

        $this->assertFalse($conflictingResult->isBound());
        $this->assertSame('identity_session_missing', (string)$conflictingResult->reason);
        $this->assertCount(0, $session->participants()->toArray());
    }

    public function testRepeatedBindingDoesNotDuplicateSessionParticipant(): void
    {
        $session = new Session('terminal');
        $session->id = 'session-1';
        $context = $this->context(AuthOutcome::success(new Profile('user-42')));
        $context->session = $session;
        $bridge = new PresenceIdentityBridge();

        $first = $bridge->bind($context);
        $second = $bridge->bind($context);

        $this->assertTrue($first->isBound());
        $this->assertTrue($second->isBound());
        $this->assertCount(1, $session->participants()->toArray());
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

        $result = (new PresenceIdentityBridge())->bind($this->context(
            AuthOutcome::success(new Profile('user-42'))
        ));

        $this->assertTrue($result->isBound());
        $this->assertSame(['before', 'after'], $actions);
        $this->assertSame(['bound'], $events);
    }

    private function context(AuthOutcome $outcome): BridgeContext
    {
        $presenceContext = new Context();
        $presenceContext->action = 'terminal.command';
        $presenceContext->session_id = 'session-1';
        $presenceContext->session_type = 'terminal';
        $presenceContext->tenant_id = 'tenant-1';

        $context = new BridgeContext();
        $context->host = 'wise';
        $context->authenticator = $outcome;
        $context->presence_context = $presenceContext;
        $context->metadata = ['participant_id' => 'user-42'];

        return $context;
    }
}
