# Wise Shell Specification

## Purpose
Provide a robust, extensible, conversational command shell and workspace for humans and agents, with predictable behavior, secure execution, and clear integration points for external interpreters and resources.

## Scope
- Interactive REPL for command entry, routing, and execution.
- Deterministic command parsing and safe execution paths.
- Interpreter integration points for shell scripting (JenSS) and templating (Vibe).
- Synthetiq as the canonical prompt/template adapter for REPL sessions.
- Input/output piping for deterministic tests (file, stream, buffer).
- Virtual root layout and file system constraints (`sys/`, `cmd/`, `usr/`, `dyn/`, `cfg/`).
- System and user configuration overlay in JenSS (`cfg/` with `usr/<user>/cfg` overrides).
- Onboarding and conversational scripts in JenSS and Vibe.
- Command suggestions and prompt hints (context-aware, resource-aware).
- Boot sequence that shows splash, progress, training/config/setup logs, and login prompt.
- ABS2 and Holoscene memory management with identity partitions and permissions.
- Resource output events (unique output and waiting state) with metadata.
- Cross-platform output refresh and process handling.

## Out of Scope (for this phase)
- Packaging and distribution strategy.
- GUI or web-based UX layers.
- Non-core features unrelated to shell behavior, interpreters, or memory/auth integration.

## Users and User Stories
- As a shell user, I can enter commands and get consistent output across platforms.
- As a tester, I can pipe a command list into Wise and capture output to a file or buffer.
- As an administrator, I can lock the virtual root and use standard folders for system files.
- As a developer, I can plug in interpreters and template engines with clear contracts.
- As an agent operator, I can configure memory, identity, and permissions for safe automation.
- As a maintainer, I can refactor core behavior safely with tests.

## Functional Requirements
- REPL accepts input and routes through CommandProcessor and interpreter(s).
- Default interpreter is configurable; JenSS is the primary target.
- Synthetiq provides the canonical prompt/template for REPL sessions via adapter.
- Vibe interpreter runs template language applications via a bridge interface.
- Input/output piping is supported for files, streams, and buffers.
- Virtual root and allowed directories are enforced by FileSystem.
- System configuration loads from `cfg/` and user overrides from `usr/<user>/cfg`.
- `cmd/` includes JenSS executables with a defined execution syntax.
- Conversational and onboarding scripts are available in JenSS/Vibe.
- JenSS scripts can model agent readiness, command suggestion training, and
  resource output envelope metadata through bridge-executed fixtures.
- Command suggestions and hints use context, spelling, and resource awareness.
- Boot sequence shows splash, progress logging, and login prompt in order.
- Working memory size is configurable at startup or mid-session.
- Memory adapter supports ABS2/Holoscene sharing across Wise/Synthetiq/Jenerator.
- Resource output events emit unique output plus waiting state and metadata.
- Output refresh works reliably on Linux and Windows terminals.
- ProcessManager handles multi-threaded or forked workloads safely.

## Non-Functional Requirements
- Predictable behavior across Windows and Linux terminals.
- Test coverage for parsing, piping, interpreter contracts, and process control.
- Blue Fission coding style using DevElation types, events, hooks, and collections.
- Minimize regressions via red-green-refactor workflows.

## External Dependencies and Integration Points
- `D:\projects\synthetiq`: core prompt/template adapter for REPL.
- `D:\projects\jenerator`: JenSS interpreter (primary shell scripting).
- `D:\projects\vibe-interpreter` or `https://github.com/bluefissiontech/vibrato`: Vibe interpreter.
- `D:\projects\chat\intellegence`: Automata intelligence and memory.
- `D:\projects\control-hub`: Opus auth alignment reference.

## Acceptance Criteria
- A baseline test suite exists for command parsing and syntax.
- Input piping works from file/stream and output is captured to file/buffer.
- Virtual root layout exists and is enforced with locked FileSystem scope.
- System and user config overlays are applied in correct precedence.
- Synthetiq adapter is configurable and tested.
- JenSS is the default interpreter with tested core behaviors.
- Vibe integration executes template apps via a defined bridge.
- Output refresh works on Linux and does not regress on Windows.
- ABS2/Holoscene memory adapter is defined and integrated.
- Resource output events emit unique output and waiting state with metadata.
- Auth, roles, and permissions align with Opus and allow injected providers.
