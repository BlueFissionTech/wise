# Script Execution

Wise discovers registered script extensions through `BridgeRegistry`. A script can still be invoked by its path or discovered command name with the bridge's basic `runFile` contract.

Use the explicit execution command when a script needs structured inputs:

```text
run <script> [--arg=value] [--cwd=path] [--stdin=value] [--env=KEY=VALUE] [--cap=name] [--timeout=ms]
```

Capabilities are denied by default. Supported capability names are `environment`, `filesystem`, `network`, `process`, and `provider`. Environment values must be supplied explicitly with `--env` and are projected only when the `environment` capability is granted. Working and include paths are projected only when `filesystem` is granted.

`ExecutionRequest` is the stable extension contract for arguments, working directory, standard input, allowlisted environment, capabilities, timeout, and cooperative cancellation. An executing bridge implements `IExecutingBridge`; bridges that only implement `IBridge` remain available through normal implicit script invocation.

JenSS execution checks cancellation and timeout before and after interpreter execution. Runtime integrations that support finer-grained cooperative checkpoints can use the request metadata carried in `BridgeContext::vars()` without changing command syntax.
