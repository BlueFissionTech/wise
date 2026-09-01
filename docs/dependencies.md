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

## Consumer installation

Composer only reads repository declarations from the root package. Consumers
of the tagged Wise alpha must therefore declare the Wise, Jenerator, Vibrato,
and Presence GitHub VCS repositories themselves and authenticate Composer with
a read-only GitHub token. Because Vibrato remains on `dev-main`, the consumer
root must permit development stability while keeping `prefer-stable` enabled.

```bash
composer config minimum-stability dev
composer config prefer-stable true
composer config repositories.wise vcs https://github.com/BlueFissionTech/wise.git
composer config repositories.jenerator vcs https://github.com/BlueFissionTech/jenerator.git
composer config repositories.vibrato vcs https://github.com/BlueFissionTech/vibrato.git
composer config repositories.presence vcs https://github.com/BlueFissionTech/presence.git
composer require bluefission/wise:0.1.0-alpha.1
```

Supply GitHub authentication through `COMPOSER_AUTH` or Composer's local auth
configuration. Never commit a token to the consuming project.

The release workflow installs each tag into an empty consumer project on PHP
8.2 and 8.3, checks the public command and authentication contracts, and proves
that Composer resolved `bluefission/wise` to the exact tagged source commit.
