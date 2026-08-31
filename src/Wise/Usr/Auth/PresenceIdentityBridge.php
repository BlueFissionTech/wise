<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Presence\Annex\TrustContract;
use BlueFission\Presence\Auth\AuthResult;
use BlueFission\Presence\Auth\Principal;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeInterface;
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

    public function name(): string
    {
        return 'wise.terminal.identity';
    }

    public function supports(BridgeContext $context): bool
    {
        return Str::lower(Str::trim((string)$context->host)) === self::HOST;
    }

    public function bind(BridgeContext $context): BridgeResult
    {
        Dev::do(self::BEFORE, [$context, $this]);

        try {
            if (!$this->supports($context)) {
                return $this->failure($context, 'unsupported_host');
            }

            $outcome = $this->authenticationOutcome($context);
            if (!$outcome instanceof AuthOutcome || !$outcome->authenticated()) {
                return $this->failure(
                    $context,
                    $outcome?->reason() ?: 'identity_not_authenticated',
                    $outcome?->metadata() ?? []
                );
            }

            $profile = $outcome->profile();
            if (!$profile instanceof Profile || Str::isEmpty(Str::trim($profile->id()))) {
                return $this->failure($context, 'identity_principal_missing');
            }

            $principal = $this->principal($profile, $outcome->metadata());
            $session = $this->session($context, $outcome->metadata());
            if (!$session instanceof Session) {
                return $this->failure($context, 'identity_session_missing', $outcome->metadata());
            }
            if ($session->isTerminated()) {
                return $this->failure($context, 'identity_session_terminated', $outcome->metadata());
            }

            $participant = $this->participant($context, $session, $principal);
            if (!$participant instanceof Participant) {
                return $this->failure($context, 'identity_session_ambiguous', $outcome->metadata());
            }

            $presenceContext = $context->context();
            $presenceContext->participant = $participant;
            $presenceContext->session = $session;

            $trustContract = $this->trustContract($context);
            if ($trustContract instanceof TrustContract) {
                $trustContract->applyTo($presenceContext);
            }

            $authResult = AuthResult::success($principal, metadata: $this->safeMetadata($outcome->metadata()));
            $result = new BridgeResult();
            $result->bound = true;
            $result->context = $presenceContext;
            $result->auth_result = $authResult;
            $result->trust_contract = $trustContract;
            $result->session = $session;
            $result->reason = 'bound';
            $result->metadata = Arr::merge($this->safeMetadata($outcome->metadata()), [
                'bridge' => $this->name(),
                'participant_id' => $participant->id(),
                'session_id' => $session->id(),
            ]);

            Dev::trigger(EventNames::BRIDGE_BOUND, [$result, $context, $this]);
            Dev::do(self::AFTER, [$result, $context, $this]);

            return $result;
        } catch (\Throwable $exception) {
            return $this->failure($context, 'identity_bridge_failed', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function authenticationOutcome(BridgeContext $context): ?AuthOutcome
    {
        $authenticator = $context->authenticator;
        if ($authenticator instanceof AuthOutcome) {
            return $authenticator;
        }

        if ($authenticator instanceof AuthProviderInterface) {
            if (!$authenticator->available() || !$authenticator->isAuthenticated()) {
                return AuthOutcome::failure('identity_not_authenticated');
            }

            $profile = $authenticator->profile();
            return $profile instanceof Profile
                ? AuthOutcome::success($profile)
                : AuthOutcome::failure('identity_principal_missing');
        }

        if ($authenticator instanceof Identity) {
            return $authenticator->isAuthenticated()
                ? AuthOutcome::success($authenticator->profile())
                : AuthOutcome::failure('identity_not_authenticated');
        }

        return $context->request instanceof AuthOutcome ? $context->request : null;
    }

    private function principal(Profile $profile, array $metadata): Principal
    {
        $principal = new Principal();
        $principal->id = Str::trim($profile->id());
        $principal->type = Str::isNotEmpty((string)Arr::getPath($metadata, 'principal_type'))
            ? Str::trim((string)Arr::getPath($metadata, 'principal_type'))
            : 'terminal_user';
        $principal->attributes = $this->safeMetadata($metadata);

        Arr::make($profile->roles())->each(function ($roleName) use ($principal): void {
            $name = Str::lower(Str::trim((string)$roleName));
            if (Str::isEmpty($name)) {
                return;
            }
            $role = new Role();
            $role->name = $name;
            $principal->roles()->addUnique($role, $name);
        });

        Arr::make($profile->permissions())->each(function ($permissionName) use ($principal): void {
            $name = Str::lower(Str::trim((string)$permissionName));
            if (Str::isEmpty($name)) {
                return;
            }
            $permission = new Permission();
            $permission->name = $name;
            $principal->permissions()->addUnique($permission, $name);
        });

        return $principal;
    }

    private function session(BridgeContext $context, array $metadata): ?Session
    {
        $presenceContext = $context->context();
        $contextSessionId = Str::trim((string)$presenceContext->session_id);
        $metadataSessionId = Str::trim((string)Arr::getPath($metadata, 'session_id'));
        $sessionId = Str::isNotEmpty($contextSessionId) ? $contextSessionId : $metadataSessionId;
        $session = $context->session;

        if ($session instanceof Session) {
            $existingId = Str::trim($session->id());
            if (Str::isEmpty($existingId)
                || (Str::isNotEmpty($sessionId) && $existingId !== $sessionId)) {
                return null;
            }
            $presenceContext->session_id = $existingId;
            $presenceContext->session_type = $session->type();
            return $session;
        }

        if (Val::isNotNull($session) || Str::isEmpty($sessionId)) {
            return null;
        }

        $sessionType = Str::trim((string)$presenceContext->session_type);
        $session = new Session(Str::isNotEmpty($sessionType) ? $sessionType : 'terminal');
        $session->id = $sessionId;
        $session->metadata = $this->safeMetadata($metadata);
        $presenceContext->session_id = $sessionId;
        $presenceContext->session_type = $session->type();

        return $session;
    }

    private function participant(
        BridgeContext $context,
        Session $session,
        Principal $principal
    ): ?Participant {
        $metadata = Arr::is($context->metadata) ? $context->metadata : [];
        $participantId = Str::trim((string)Arr::getPath($metadata, 'participant_id'));
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

    private function trustContract(BridgeContext $context): ?TrustContract
    {
        $manifest = Arr::is($context->annex_manifest) ? $context->annex_manifest : [];
        if (Arr::isEmpty($manifest)) {
            return null;
        }

        $contract = new TrustContract();
        $contract->manifest = $manifest;
        Arr::make([
            'strictness_level',
            'compliance',
            'data_residency',
            'scopes',
            'redaction_policy',
        ])->each(function ($field) use ($contract, $manifest): void {
            if (Arr::hasKey($manifest, (string)$field)) {
                $contract->field((string)$field, $manifest[$field]);
            }
        });

        return $contract;
    }

    private function failure(BridgeContext $context, string $reason, array $metadata = []): BridgeResult
    {
        $result = new BridgeResult();
        $result->bound = false;
        $result->context = $context->context();
        $result->auth_result = AuthResult::failure($reason, metadata: $this->safeMetadata($metadata));
        $result->session = $context->session instanceof Session ? $context->session : null;
        $result->reason = $reason;
        $result->metadata = Arr::merge($this->safeMetadata($metadata), [
            'bridge' => $this->name(),
        ]);

        Dev::trigger(EventNames::BRIDGE_FAILED, [$result, $context, $this]);
        Dev::do(self::FAILURE, [$result, $context, $this]);

        return $result;
    }

    private function safeMetadata(array $metadata): array
    {
        return Arr::make($metadata)
            ->filter(fn ($value, $key) => !Arr::has(self::SENSITIVE_METADATA, Str::lower((string)$key), true))
            ->toArray();
    }
}
