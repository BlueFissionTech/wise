# Authentication Providers

Wise owns `AuthProviderInterface` as its authentication boundary. The default
kernel behavior remains the built-in DevElation authenticator. An application
may inject another provider as the final `Kernel` constructor argument without
changing command or terminal code.

## Presence provider

`PresenceAuthProvider` adapts a configured Presence authenticator registry to
Wise profiles. Wise installs Presence as a runtime dependency while leaving
provider activation and policy configuration to the host.

Presence is distributed from its authenticated GitHub VCS repository. Composer
must receive a GitHub token with read-only access to the repository outside
source control. Wise registers the VCS source and requires the immutable
`bluefission/presence:^0.1.0-alpha.1` release. Development and CI contract tests
therefore exercise the real upstream value objects and registry.

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

## Terminal identity bridge

`PresenceIdentityBridge` maps an already-authenticated Wise profile into
Presence's canonical `Principal`, `AuthResult`, participant, and `Session`
contracts. It does not authenticate credentials, prompt, render, resume a
continuation, or mutate command results. Those responsibilities remain with the
host.

The bridge consumes the reviewed Presence `0.1.0-alpha.3` VCS release. Wise does
not define compatibility bridge types or depend on an unreleased Presence
branch.

Construct `PresenceIdentityBridge` with the current `AuthOutcome` or configured
Wise authentication provider, an optional Presence session, and optional Annex
manifest. Pass only opaque host, tenant, actor, session, correlation, revision,
and sanitized metadata values through the immutable Presence `BridgeContext`.
The bridge fails closed when authentication, principal, tenant, or session state
is missing or conflicting and returns only immutable Presence result snapshots.

Lifecycle integrations can observe `wise.identity.bridge.before`,
`wise.identity.bridge.after`, and `wise.identity.bridge.failure` through
DevElation actions. Successful and failed bindings also emit Presence's
`presence.bridge.bound` and `presence.bridge.failed` events.
- Missing principals, rejected credentials, role mapping, permission checks,
  local logout, and revocation failure have deterministic test coverage.

No provider credentials belong in source control. Construct authenticators and
session services from the host's secret/configuration system and inject them.
