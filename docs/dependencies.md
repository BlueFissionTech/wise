# Dependency baselines

Wise consumes reviewed releases for runtime dependencies whenever a release is
available. Development constraints are retained only while an upstream package
has no consumable release.

The current intelligence baseline is:

- `bluefission/develation:~1.3.43.0`, held below `v1.3.44` until the released
  BlueCore engine signature is compatible with the newer application singleton
  contract.
- `bluefission/automata:^1.0.0-alpha.5`, distributed through Packagist.
- `bluefission/jenerator:^0.1.0-alpha.1`, distributed as a tagged private GitHub
  VCS release.

The Jenerator repository entry remains necessary for Composer discovery because
the package is not registered on Packagist. Consumers must authenticate Composer
for private GitHub access. Moving from `dev-main` to the tagged Jenerator release
does not change Wise's JenSS bridge API; it makes installs reproducible at the
reviewed release commit.

`bluefission/vibrato` remains on its existing development constraint until a
tagged release exposes the Vibe interpreter contract currently consumed by Wise.
