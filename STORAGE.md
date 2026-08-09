# Resource Storage

Wise stores persisted resource data under `storage/` in the current workspace by default. Set `WISE_STORAGE_ROOT` to an explicit directory when the shell should use another location:

```bash
WISE_STORAGE_ROOT=/var/lib/wise php terminal.php
```

Hosts can also inject a `BlueFission\Wise\Sys\StorageRoot` into resource constructors. The legacy `OPUS_ROOT` constant remains supported as a compatibility fallback and resolves to its `storage/` directory, but new integrations should use the Wise-owned environment or object contract.
