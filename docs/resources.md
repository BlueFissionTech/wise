# Resource Catalog

`ResourceCatalog` is the authoritative package contract for resource class
discovery. Hosts should consume `definitions()` or `describe()` instead of
constructing class names from resource identifiers.

Canonical system and command capabilities resolve to `SystemResource` and
`CommandResource`. The website capability resolves to the PSR-4-aligned
`WebBrowserResource`. Legitimate command aliases such as `web` and
`filemanager` resolve through `aliases()` while preserving one canonical class
entry.

Message, transcript, and generic resource-management capabilities currently
have no package-owned PHP resource class. They are returned as `unavailable`
with the `not_implemented` reason. Required hosts should report that status;
optional hosts may omit the capability atomically. They must not construct or
register compatibility classes for those identifiers.

`missingClasses()` validates that every available catalog entry is loadable.
The CI authoritative Composer autoload step and resource catalog tests enforce
the class/file contract on PHP 8.2 and 8.3.

## Profile-private resources

`FunctionResource`, `GoalResource`, and `ProfileStepResource` provide the
host-neutral private profile contract. A host binds each resource instance to a
`ProfileScope` containing tenant, application, profile, owner identity, and owner
type. Correlation and session identifiers remain in `RuntimeContext` and are
never part of the persisted scope.

Descriptor discovery and execution both require the action capability. Access
from another profile additionally requires `wise.profile.cross_scope` and an
explicit target scope key in `profile_scope_grants`; neither condition grants
access by itself.

The existing `StepResource` remains available as the legacy combined goal/step
surface. New integrations should use `goal` and `profile_step`, which persist
goal lifecycle and ordered steps as separate records.
