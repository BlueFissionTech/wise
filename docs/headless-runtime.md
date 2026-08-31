# Headless Command Runtime

`ICommandRuntime` is the package-owned boundary for application, worker, and
terminal adapters. It composes existing command parsing, registered resource
dispatch, native commands, and script bridges without owning terminal input or
rendering.

## Construction

```php
use BlueFission\Wise\Cmd\CommandRuntime;
use BlueFission\Wise\Cmd\ICommandRuntime;

$runtime = new CommandRuntime($commandProcessor, $commandHandler, $kernel);
$application->bind(ICommandRuntime::class, CommandRuntime::class);
```

The command processor is required. The native handler and kernel are optional,
which allows resource-only workers to use the same interface. Containers may
bind `ICommandRuntime` to `CommandRuntime` and provide the explicit constructor
arguments according to their normal service configuration.

## Per-request context

`RuntimeContext` carries correlation metadata, arguments, an application working
directory, allowlisted environment values, actor identity, capabilities,
timeout/deadline policy, and a cancellation callback. It does not read host
globals or change the process working directory.

Environment values are passed to script bridges only when both conditions hold:

- The key appears in the context environment allowlist.
- The context declares the `environment` capability.

A working directory similarly requires the `filesystem` capability. Missing
capabilities fail before bridge execution.

## Execution

```php
$runtimeResult = $runtime->execute($commandRequest, $runtimeContext);
$scriptResult = $runtime->executeScript('cmd/status.jss', $runtimeContext);
```

`RuntimeResult::result()` returns the authoritative `CommandResult`. If a
resource already returns a `CommandResult`, its status, output, diagnostics,
exit code, prompt state, and continuation token are preserved. Host metadata is
merged once through `CommandResult::withMetadata()`. The method returns a new
result, leaves the original unchanged, and gives supplied host metadata
precedence when a key already exists.

`RuntimeResult::frames()` exposes typed status, output, diagnostic, and prompt
frames. A terminal adapter may render these frames; an application adapter may
serialize them. The runtime itself never reads terminal input, writes output,
emits ANSI control sequences, clears a screen, or exits the process.

## Discovery

`discover()` returns stable arrays for registered resource commands, native
commands, and supported script extensions. Discovery does not boot a console or
execute a command.

Confirmation continuations remain explicit and replay-safe through
`CommandRequest::resume()`. The host is responsible for collecting approval and
submitting the continuation exactly once.
