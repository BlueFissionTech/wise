# Wise Examples

The examples in this directory are executable reference patterns for the Wise
virtual environment. They are intended to stay deterministic, local, and safe to
run on a clean checkout.

## Layout

- `terminal.php` boots the Wise console with the current kernel, command
  processor, memory, filesystem, display, JenSS, and Vibe bridge APIs.
- `batch-commands.txt` is a minimal non-interactive command stream for smoke
  testing the terminal entrypoint.
- `bridge-smoke.php` executes the example JenSS and Vibe files through the
  bridge contracts without starting an interactive terminal session.
- `root/cmd` contains command scripts that are resolved from the virtual
  filesystem.
- `root/cfg` and `root/usr/console/cfg` contain system and user config overlays.
- `root/sys/res` contains scripted resource templates.

Advanced JenSS intelligence namespaces are intentionally not used by the Wise
runtime examples until they are packaged as executable modules. Keep those ideas
as parser-surface fixtures in the interpreter project; Wise examples should run
through the installed bridge contracts.

## Smoke Checks

Run the bridge smoke check:

```bash
php examples/bridge-smoke.php
```

Run the terminal in deterministic batch mode:

```bash
php terminal.php --input-file=examples/batch-commands.txt --display-mode=static --output-mode=console
```

Run the focused PHPUnit coverage for examples:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Examples
```

## Authoring Pattern

Command examples should:

- live under `root/cmd` and use the extension for their interpreter contract;
- keep prompts finite and deterministic so they can be driven by scripted input;
- avoid network calls, credentials, and machine-specific paths;
- surface output that proves the flow ran through Wise rather than only parsing;
- add or update tests when a new interpreter feature is exercised.

Config examples should:

- live under `root/cfg` for system defaults or `root/usr/<profile>/cfg` for user
  overlays;
- prefer small maps with explicit keys over inferred defaults;
- execute cleanly through the bridge even when they do not produce output.
