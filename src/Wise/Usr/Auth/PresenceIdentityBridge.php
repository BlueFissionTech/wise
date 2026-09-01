<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Presence\Annex\AnnexAdapter;
use BlueFission\Presence\Annex\TrustContract;
use BlueFission\Presence\Auth\Principal;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeInterface;
use BlueFission\Presence\Bridge\BridgeReason;
use BlueFission\Presence\Bridge\BridgeResult;
use BlueFission\Presence\Policy\Permission;
use BlueFission\Presence\Policy\Role;
use BlueFission\Presence\Session\Participant;
use BlueFission\Presence\Session\Session;
use BlueFission\Presence\Support\EventNames;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Usr\Identity;
use BlueFission\Wise\Usr\Profile;

final class PresenceIdentityBridge extends Obj implements BridgeInterface
{
    public const HOST = 'wise';
    public const BEFORE = 'wise.identity.bridge.before';
    public const AFTER = 'wise.identity.bridge.after';
    public const FAILURE = 'wise.identity.bridge.failure';

    private const SENSITIVE_METADATA = [
        'credential',
        'password',
        'secret',
        'token',
    ];

    private AuthOutcome|AuthProviderInterface|Identity|null $authenticator;
    private ?Session $session;
    private array $annexManifest;

    public function __construct(
        AuthOutcome|AuthProviderInterface|Identity|null $authenticator = null,
        ?Session $session = null,
        array $annexManifest = []
    ) {
        parent::__construct();

        $this->authenticator = $authenticator;
        $this->session = $session;
        $this->annexManifest = $annexManifest;
    }

    public function name(): string
    {
        return 'wise.terminal.identity';
    }

    public function supports(BridgeContext $context): bool
    {
        return Str::make($context->host())->trim()->lower()->val() === self::HOST;
    }

    public function bind(BridgeContext $context): BridgeResult
    {
        Dev::do(self::BEFORE, [$context, $this]);

        try {
            if (!$this->supports($context)) {
                return $this->failure($context, BridgeReason::UNAVAILABLE, 'unsupported_host');
            }

            if ($context->isExpired()) {
                return $this->failure($context, BridgeReason::EXPIRED, 'identity_context_expired');
            }

            $outcome = $this->authenticationOutcome();
            if (!$outcome instanceof AuthOutcome || !$outcome->authenticated()) {
                return $this->failure(
                    $context,
                    BridgeReason::UNAUTHORIZED,
                    $outcome?->reason() ?: 'identity_not_authenticated',
                    $outcome?->metadata() ?? []
                );
            }

            $profile = $outcome->profile();
            if (!$profile instanceof Profile || Str::make($profile->id())->trim()->isEmpty()) {
                return $this->failure(
                    $context,
                    BridgeReason::INVALID,
                    'identity_principal_missing'
                );
            }

            if ($this->tenantConflicts($context, $outcome->metadata())) {
                return $this->failure(
                    $context,
                    BridgeReason::TENANT_MISMATCH,
                    'identity_tenant_mismatch',
                    $outcome->metadata()
                );
            }

            $principal = $this->principal($profile, $outcome->metadata());
            $session = $this->resolveSession($context, $outcome->metadata());
            if (!$session instanceof Session) {
                return $this->failure(
                    $context,
                    BridgeReason::INVALID,
                    'identity_session_missing',
                    $outcome->metadata()
                );
            }
            if ($session->isTerminated()) {
                return $this->failure(
                    $context,
                    BridgeReason::EXPIRED,
                    'identity_session_terminated',
                    $outcome->metadata()
                );
            }

            $participant = $this->participant($context, $session, $principal);
            if (!$participant instanceof Participant) {
                return $this->failure(
                    $context,
                    BridgeReason::INVALID,
                    'identity_session_ambiguous',
                    $outcome->metadata()
                );
            }

            $presenceContext = $context->context();
            $presenceContext->participant = $participant;
            $presenceContext->session = $session;

            $trustContract = $this->trustContract();
            if ($trustContract instanceof TrustContract) {
                $trustContract->applyTo($presenceContext);
            }

            $result = new BridgeResult(
                bound: true,
                reasonCode: BridgeReason::BOUND,
                principal: $principal,
                sessionMetadata: $this->sessionMetadata($context, $session, $participant),
                authenticationRevision: $context->authenticationRevision(),
                sessionRevision: $context->sessionRevision(),
                issuedAt: $context->issuedAt(),
                expiresAt: $context->expiresAt(),
                metadata: Arr::merge($this->safeMetadata($outcome->metadata()), [
                    'bridge' => $this->name(),
                    'correlation_id' => $context->correlationId(),
                    'trust_contract' => $this->trustMetadata($trustContract),
                ])
            );

            Dev::trigger(EventNames::BRIDGE_BOUND, [$result, $context, $this]);
            Dev::do(self::AFTER, [$result, $context, $this]);

            return $result;
        } catch (\Throwable $exception) {
            return $this->failure(
                $context,
                BridgeReason::UNAVAILABLE,
                'identity_bridge_failed',
                ['exception' => $exception::class]
            );
        }
    }

    private function authenticationOutcome(): ?AuthOutcome
    {
        if ($this->authenticator instanceof AuthOutcome) {
            return $this->authenticator;
        }

        if ($this->authenticator instanceof AuthProviderInterface) {
            if (!$this->authenticator->available() || !$this->authenticator->isAuthenticated()) {
                return AuthOutcome::failure('identity_not_authenticated');
            }

            $profile = $this->authenticator->profile();
            return $profile instanceof Profile
                ? AuthOutcome::success($profile)
                : AuthOutcome::failure('identity_principal_missing');
        }

        if ($this->authenticator instanceof Identity) {
            return $this->authenticator->isAuthenticated()
                ? AuthOutcome::success($this->authenticator->profile())
                : AuthOutcome::failure('identity_not_authenticated');
        }

        return null;
    }

    private function principal(Profile $profile, array $metadata): Principal
    {
        $principal = new Principal();
        $principal->id = Str::make($profile->id())->trim()->val();
        $principalType = Str::make((string)Arr::getPath($metadata, 'principal_type'))->trim();
        $principal->type = $principalType->isNotEmpty() ? $principalType->val() : 'terminal_user';
        $principal->attributes = $this->safeMetadata($metadata);

        Arr::make($profile->roles())->each(function ($roleName) use ($principal): void {
            $name = Str::make((string)$roleName)->trim()->lower()->val();
            if (Str::isEmpty($name)) {
                return;
            }
            $role = new Role();
            $role->name = $name;
            $principal->roles()->addUnique($role, $name);
        });

        Arr::make($profile->permissions())->each(function ($permissionName) use ($principal): void {
            $name = Str::make((string)$permissionName)->trim()->lower()->val();
            if (Str::isEmpty($name)) {
                return;
            }
            $permission = new Permission();
            $permission->name = $name;
            $principal->permissions()->addUnique($permission, $name);
        });

        return $principal;
    }

    private function resolveSession(BridgeContext $context, array $metadata): ?Session
    {
        $contextSessionId = Str::make($context->sessionId())->trim()->val();
        $metadataSessionId = Str::make((string)Arr::getPath($metadata, 'session_id'))->trim()->val();
        $sessionId = Str::isNotEmpty($contextSessionId) ? $contextSessionId : $metadataSessionId;

        if ($this->session instanceof Session) {
            $existingId = Str::make($this->session->id())->trim()->val();
            if (Str::isEmpty($existingId)
                || (Str::isNotEmpty($sessionId) && $existingId !== $sessionId)) {
                return null;
            }

            return $this->session;
        }

        if (Val::isNotNull($this->session) || Str::isEmpty($sessionId)) {
            return null;
        }

        $sessionType = Str::make((string)Arr::getPath($context->metadata(), 'session_type'))->trim();
        $this->session = new Session($sessionType->isNotEmpty() ? $sessionType->val() : 'terminal');
        $this->session->id = $sessionId;
        $this->session->metadata = $this->safeMetadata($metadata);

        return $this->session;
    }

    private function participant(
        BridgeContext $context,
        Session $session,
        Principal $principal
    ): ?Participant {
        $metadata = $context->metadata();
        $participantId = Str::make((string)Arr::getPath($metadata, 'participant_id'))->trim()->val();
        $participantId = Str::isNotEmpty($participantId) ? $participantId : $principal->id();
        if (Str::isEmpty($participantId)) {
            return null;
        }

        if ($session->participants()->has($participantId)) {
            $participant = $session->participants()->get($participantId);
            return $participant instanceof Participant
                && $participant->principal()->id() === $principal->id()
                ? $participant
                : null;
        }

        $participant = new Participant();
        $participant->id = $participantId;
        $participant->principal = $principal;
        $participant->metadata = $this->safeMetadata($metadata);
        foreach ($principal->roles() as $key => $role) {
            $participant->roles()->addUnique($role, $key);
        }
        foreach ($principal->permissions() as $key => $permission) {
            $participant->permissions()->addUnique($permission, $key);
        }
        $session->addParticipant($participant);

        return $participant;
    }

    private function trustContract(): ?TrustContract
    {
        if (Arr::isEmpty($this->annexManifest)) {
            return null;
        }

        return (new AnnexAdapter())->ingest($this->annexManifest);
    }

    private function failure(
        BridgeContext $context,
        string $reasonCode,
        string $wiseReason,
        array $metadata = []
    ): BridgeResult {
        $result = new BridgeResult(
            bound: false,
            reasonCode: $reasonCode,
            sessionMetadata: $this->session instanceof Session
                ? ['id' => $this->session->id(), 'type' => $this->session->type()]
                : [],
            authenticationRevision: $context->authenticationRevision(),
            sessionRevision: $context->sessionRevision(),
            issuedAt: $context->issuedAt(),
            expiresAt: $context->expiresAt(),
            metadata: Arr::merge($this->safeMetadata($metadata), [
                'bridge' => $this->name(),
                'wise_reason' => $wiseReason,
            ])
        );

        Dev::trigger(EventNames::BRIDGE_FAILED, [$result, $context, $this]);
        Dev::do(self::FAILURE, [$result, $context, $this]);

        return $result;
    }

    private function tenantConflicts(BridgeContext $context, array $metadata): bool
    {
        $contextTenant = Str::make($context->tenantId())->trim()->val();
        $identityTenant = Str::make((string)Arr::getPath($metadata, 'tenant_id'))->trim()->val();

        return Str::isNotEmpty($contextTenant)
            && Str::isNotEmpty($identityTenant)
            && $contextTenant !== $identityTenant;
    }

    private function sessionMetadata(
        BridgeContext $context,
        Session $session,
        Participant $participant
    ): array {
        return Arr::merge($this->safeMetadata(Arr::make($session->metadata)->toArray()), [
            'id' => $session->id(),
            'type' => $session->type(),
            'participant_id' => $participant->id(),
            'tenant_id' => $context->tenantId(),
        ]);
    }

    private function trustMetadata(?TrustContract $contract): array
    {
        if (!$contract instanceof TrustContract) {
            return [];
        }

        return [
            'strictness_level' => $contract->strictness(),
            'compliance' => (array)$contract->compliance,
            'data_residency' => (array)$contract->data_residency,
            'scopes' => (array)$contract->scopes,
            'redaction_policy' => (array)$contract->redaction_policy,
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        return Arr::make($metadata)
            ->filter(fn ($value, $key) => !Arr::has(
                self::SENSITIVE_METADATA,
                Str::make((string)$key)->lower()->val(),
                true
            ))
            ->toArray();
    }
}
