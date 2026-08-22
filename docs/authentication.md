# Authentication Providers

Wise owns `AuthProviderInterface` as its authentication boundary. The default
kernel behavior remains the built-in DevElation authenticator. An application
may inject another provider as the final `Kernel` constructor argument without
changing command or terminal code.

## Presence provider

`PresenceAuthProvider` adapts a configured Presence authenticator registry to
Wise profiles. Presence remains optional so clean CLI and headless installations
do not require authentication infrastructure.

The host must provide a registry with the authenticators required by its policy:

```php
use BlueFission\Presence\Auth\AuthenticatorRegistry;
use BlueFission\Wise\Usr\Auth\PresenceAuthProvider;

$registry = new AuthenticatorRegistry();
$registry->register($configuredAuthenticator);

$provider = new PresenceAuthProvider(
    $registry,
    logoutHandler: $revokeSession
);

$kernel = new Kernel(
    $processManager,
    $commandProcessor,
    $memoryManager,
    $fileSystemManager,
    $interpreter,
    $console,
    $sessionStorage,
    $dataStorage,
    $ipc,
    $provider
);
```

The default credential and context factories map identifier, secret, credential
type, request metadata, session id, and tenant id to Presence value objects.
Custom factories may be injected for other credential or context policies.

Successful principals map their id, roles, and permissions into a Wise
`Profile`. Authentication reasons and non-secret metadata are preserved.
Credentials and secrets are never added to the outcome metadata.

## Logout and sessions

Presence authentication registries do not own host session revocation. Supply a
logout handler that revokes the authenticated session through the host's session
service. The handler receives the last successful `AuthOutcome`.

Wise always clears local provider identity in a `finally` block. A revocation
exception is propagated after local cleanup so the caller can report that the
remote session may still exist. Without a logout handler, logout remains local.

## Failure behavior

- Missing Presence classes or an unconfigured registry produce an unavailable
  or unsupported-provider outcome; the legacy authenticator remains available
  when no provider is injected.
- Authentication exceptions are normalized to
  `presence_authentication_failed` without exposing credential values.
- Missing principals, rejected credentials, role mapping, permission checks,
  local logout, and revocation failure have deterministic test coverage.

No provider credentials belong in source control. Construct authenticators and
session services from the host's secret/configuration system and inject them.
