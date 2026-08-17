# Authentication Providers

Wise authentication is exposed through `AuthProviderInterface`. The default CLI authenticator remains active when no advanced provider is installed or configured.

`PresenceAuthProvider` maps a configured Presence authenticator registry into Wise `Profile` identity, role, and permission data. It does not create authenticators or read credentials from the environment. Applications inject the configured registry and may inject credential/context factories for custom credential types.

Presence is optional until a tagged package is available through Packagist. Wise does not add a VCS-only dependency. When the package is absent, `NullAuthProvider` and the existing authenticator provide deterministic clean-install behavior.

Secrets remain confined to `AuthRequest` and are not copied into outcomes, profiles, diagnostics, or metadata. Session and tenant identifiers may be supplied through the request context by the owning application.
