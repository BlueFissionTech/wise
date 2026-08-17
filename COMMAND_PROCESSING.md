# Command Processing

Wise separates command processing from terminal presentation. Application, HTTP, chat, worker, and test hosts use `BlueFission\Wise\Cmd\ICommandProcessor`; interactive terminals remain responsible for input loops, ANSI rendering, screen clearing, and process lifecycle.

## Headless API

Construct `CommandProcessor` with a DevElation `Storage` implementation, then call `process()` with command text, a `Command`, an associative parser payload, or a `CommandRequest`.

```php
use BlueFission\Wise\Cmd\CommandRequest;

$processor = new CommandProcessor($sessionStorage);
$result = $processor->process(new CommandRequest(
    ['operator' => 'list', 'objects' => ['resource']],
    context: ['request_id' => 'request-42']
));

if ($result->confirmationRequired()) {
    // Ask through the host's own interaction channel, then resume once.
    $result = $processor->process(CommandRequest::resume(
        $result->continuationToken(),
        approved: true
    ));
}

$payload = $result->toArray();
```

Structured parser payloads use these package-owned fields:

- `verb`, `operator`, or `behavior`
- `resources`, `objects`, `resource`, or `object`
- `args`, `values`, or `literals`

Use `CommandRequest::parse()` for parse-only validation. Execution is the default. `CommandResult` reports `completed`, `parsed`, `confirmation_required`, `invalid`, or `failed`, plus output, command data, description, exit code, diagnostics, and caller metadata.

Parse-only requests may describe resources that are not registered yet. Execute requests with an unknown structured resource return an `invalid` result with a `resource_not_found` diagnostic before service dispatch.

`handle(string)` remains available for backward-compatible interactive and conversational callers. New hosts should use `process()` so they never infer state or status from rendered text.

Confirmation results include an opaque continuation token. The pending attempt does not execute the command. Resume with `CommandRequest::resume($token, $approved)` exactly once; consumed and invalid tokens return deterministic invalid results, preventing approval replay from executing side effects twice. Continuations require the same session-scoped storage instance as the original request.

## Host Boundary

The processor does not read standard input, emit ANSI sequences, clear a screen, start a loop, or terminate a process. Hosts own those effects. Host registrations should inject a request-scoped or session-scoped storage source so command history and confirmation state do not leak between users.
