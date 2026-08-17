# Intelligence And Orchestration

Wise exposes intelligence execution through the package-owned `IOrchestrator` contract. Commands and resources submit an `OrchestrationRequest` and consume an `OrchestrationOutcome`; they do not depend on prompt text or a concrete agent runtime.

`AutomataOrchestrator` delegates orchestration patterns, session scope, capabilities, and agent state to released Automata APIs. Wise owns only shell-facing normalization:

- `PersonaContext` maps identity, roles, permissions, and application attributes into a stable context.
- `OrchestrationRequest` carries the task, workers, pattern, scoped context, capabilities, prior state, and configuration.
- `OrchestrationOutcome` preserves status, output, worker results, conflicts, confidence, metadata, persona, session id, and a recoverable state snapshot.
- `NullOrchestrator` provides a deterministic unavailable result for disabled or minimal runtimes.

The adapter emits DevElation hooks at `wise.int.orchestration.before`, `wise.int.orchestration.after`, and `wise.int.orchestration.failed`. Hook handlers must not place secrets in context or result metadata.

Workers are ordinary Automata orchestration workers. They should return structured arrays with `output`, optional `confidence`, and optional `metadata`. Host code remains responsible for registering tools and enforcing authorization before a worker performs mutations.

Persona and session context are snapshots. They do not grant permissions by themselves. The request capability list controls the Automata session scope, while resource and command authorization remains at the Wise execution boundary.
