# Wise Shell Architecture

## Overview
Wise Shell is an event-driven, modular REPL. The core kernel orchestrates
command parsing, resource dispatch, interpreter execution, output rendering,
and process control.

## Core Modules
- `src/Wise/Arc`: Kernel and orchestration (bootstraps subsystems).
- `src/Wise/Cmd`: CommandProcessor and command routing.
- `src/Wise/Sys`: ProcessManager, MemoryManager, DisplayManager, and system IO.
- `src/Wise/Res`: Built-in resources/commands.
- `src/Wise/Exe`: Execution helpers and runners.
- `src/Wise/Cli`: CLI entrypoints for running the shell.

## Command Flow
1. REPL receives input.
2. CommandProcessor parses intent and routes to a resource/action.
3. Interpreter (default: jenss) handles script execution when needed.
4. ProcessManager and Async coordinate long-running or parallel work.
5. DisplayManager renders output and refreshes the terminal view.

## Resource Output Events
Resources emit agent-readable output envelopes through:

- `wise.resource.output` for a unique output change.
- `wise.resource.output.refresh` for a repeated render of the same output.
- `wise.resource.waiting` when the output expects follow-up input.

Output payloads include `output_id`, `resource_name`, `action`, `status`,
`waiting`, `completed`, `timestamp`, `hash`, preview text, optional
`full_output`, and optional `semantic_metadata`. Repeated renders reuse the
same `output_id` and set `repeated=true` on the refresh event. Waiting payloads
carry the same `output_id`, options, prompt state metadata, and
`status=waiting` so headless agents can preserve prompt recovery state without
scraping terminal text.

## Interpreter Integration
- Interpreters implement a stable contract (interface) and are injected into
  the command pipeline.
- Synthetiq acts as the canonical prompt template for REPL interactions.
- Vibe provides template language execution for higher-level workflows.

## Output Refresh and Terminal IO
- DisplayManager is responsible for output and redraw behavior.
- Output refresh must be cross-platform; Linux behavior is a priority fix,
  with Windows kept stable.

## Process and Concurrency
- ProcessManager orchestrates child processes and forked work.
- Async coordinates non-blocking execution and event-driven updates.

## Extension Points
- Register new resources under `src/Wise/Res`.
- Add or replace interpreters in the kernel wiring.
- Extend command parsing rules with tests to lock behavior.

## Agent Integration Boundary

Wise hosts intelligence providers behind `IOrchestrator`. The public API stays
synchronous and provider-neutral. `OrchestrationEnvelope` adds a versioned
request/outcome contract without replacing the existing
`OrchestrationRequest` and `OrchestrationOutcome` types.

The envelope separates three concerns:

1. Wise contract and host context: subject, session, lineage, budgets, and
   lifecycle support.
2. Provider identity: package source/version plus the exact upstream contract
   version and adapter-bound features.
3. Results: the Wise status remains distinct from the provider's original
   status, code, evidence, and diagnostics.

Injected providers cannot execute commands through this contract.
`CommandPolicy` remains the host-side authority boundary. Provider confidence,
ranking, or advice cannot widen permissions. The current lifecycle explicitly
does not promise cancellation, streaming, resume, automatic effect retries, or
exactly-once effects.

Execution receipts separately record host-requested termination, confirmed
stop evidence, and uncertain in-flight work. These receipts do not turn
provider feature discovery into authority: effect authorization and
idempotency remain host-owned, and no effect is attributed after a denied or
confirmed terminal boundary. Missing execution facts remain `null` rather than
being normalized into negative or empty evidence.

The Automata adapter records the installed `bluefission/automata` version and
the released `AgentIntegrationContract` version at runtime. It records only
feature identifiers actually bound by the adapter and does not translate
generic orchestration outcomes into strategy-routing outcomes.
