<?php

namespace BlueFission\Wise\Usr\Auth;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Presence\Auth\AuthenticatorRegistry;
use BlueFission\Presence\Auth\Credential;
use BlueFission\Presence\Support\Context;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Usr\Profile;

class PresenceAuthProvider extends Obj implements AuthProviderInterface
{
    private const REGISTRY = AuthenticatorRegistry::class;
    private const CREDENTIAL = Credential::class;
    private const CONTEXT = Context::class;

    private ?object $registry;
    private $credentialFactory;
    private $contextFactory;
    private $logoutHandler;
    private ?AuthOutcome $outcome = null;

    public function __construct(
        ?object $registry = null,
        ?callable $credentialFactory = null,
        ?callable $contextFactory = null,
        ?callable $logoutHandler = null
    ) {
        parent::__construct();
        $this->registry = $registry ?? $this->defaultRegistry();
        $this->credentialFactory = $credentialFactory ?? $this->defaultCredentialFactory();
        $this->contextFactory = $contextFactory ?? $this->defaultContextFactory();
        $this->logoutHandler = $logoutHandler;
    }

    public function available(): bool
    {
        return Val::is($this->registry)
            && Func::isCallable([$this->registry, 'authenticate'])
            && Func::isCallable($this->credentialFactory)
            && Func::isCallable($this->contextFactory);
    }

    public function authenticate(AuthRequest $request): AuthOutcome
    {
        if (!$this->available()) {
            return $this->outcome = AuthOutcome::failure('presence_unavailable');
        }

        try {
            $credential = ($this->credentialFactory)($request);
            $context = ($this->contextFactory)($request);
            $result = $this->registry->authenticate($credential, $context);
        } catch (\Throwable $exception) {
            return $this->outcome = AuthOutcome::failure('presence_authentication_failed', [
                'exception' => $exception::class,
            ]);
        }

        if (!Val::make($result)->check('is_object')
            || !Func::isCallable([$result, 'isSuccessful'])
            || !$result->isSuccessful()) {
            return $this->outcome = AuthOutcome::failure(
                (string)($result->reason ?? 'authentication_failed'),
                Arr::is($result->metadata ?? null) ? $result->metadata : []
            );
        }

        $principal = $result->principal ?? null;
        if (!Val::make($principal)->check('is_object') || !Func::isCallable([$principal, 'id'])) {
            return $this->outcome = AuthOutcome::failure('presence_principal_missing');
        }

        $profile = new Profile(
            (string)$principal->id(),
            $this->namesFromCollection(Func::isCallable([$principal, 'roles']) ? $principal->roles() : null, ['user']),
            $this->namesFromCollection(Func::isCallable([$principal, 'permissions']) ? $principal->permissions() : null)
        );

        return $this->outcome = AuthOutcome::success($profile, Arr::merge(
            Arr::is($result->metadata ?? null) ? $result->metadata : [],
            ['provider' => 'presence']
        ));
    }

    public function logout(): void
    {
        try {
            if (Func::isCallable($this->logoutHandler) && Val::is($this->outcome)) {
                ($this->logoutHandler)($this->outcome);
            }
        } finally {
            $this->outcome = null;
        }
    }

    public function isAuthenticated(): bool
    {
        return Val::is($this->outcome) && $this->outcome->authenticated();
    }

    public function profile(): ?Profile
    {
        return $this->outcome?->profile();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->profile()?->hasPermission($permission) ?? false;
    }

    private function defaultRegistry(): ?object
    {
        if (!class_exists(self::REGISTRY)) {
            return null;
        }

        $class = self::REGISTRY;
        return new $class();
    }

    private function defaultCredentialFactory(): ?callable
    {
        if (!class_exists(self::CREDENTIAL)) {
            return null;
        }

        return static function (AuthRequest $request): object {
            $class = self::CREDENTIAL;
            $credential = new $class();
            $credential->field('type', $request->type());
            $credential->field('identifier', $request->identifier());
            $credential->field('secret', $request->secret());
            $credential->field('metadata', $request->context());
            return $credential;
        };
    }

    private function defaultContextFactory(): ?callable
    {
        if (!class_exists(self::CONTEXT)) {
            return null;
        }

        return static function (AuthRequest $request): object {
            $class = self::CONTEXT;
            $context = new $class();
            $context->field('action', 'authenticate');
            $context->field('session_id', (string)($request->context()['session_id'] ?? ''));
            $context->field('tenant_id', (string)($request->context()['tenant_id'] ?? ''));
            $context->field('metadata', $request->context());
            return $context;
        };
    }

    private function namesFromCollection(mixed $collection, array $default = []): array
    {
        if (Val::make($collection)->check('is_object') && Func::isCallable([$collection, 'toArray'])) {
            $collection = $collection->toArray(true);
        }
        if (!Arr::is($collection)) {
            return $default;
        }

        $names = [];
        foreach ($collection as $item) {
            $name = is_object($item) ? ($item->name ?? null) : $item;
            if (Val::isNotEmpty($name)) {
                $names[] = Str::make((string)$name)->trim()->lower()->val();
            }
        }

        return Arr::isNotEmpty($names) ? Arr::make($names)->unique()->toArray() : $default;
    }
}
