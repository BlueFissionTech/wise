# Wise Shell Features

## Current Capabilities
- Event-driven command execution and routing.
- Conversational command handling via the command processor.
- Async processing for non-blocking workflows.
- Command aliases and extensible resources.
- File system operations and memory management helpers.
- Process management and system IO facilities.
- JenSS bridge fixtures for command scripts, config scripts, agent readiness,
  command prediction training, and resource event envelope shaping.

## Planned and Prioritized Features
- Synthetiq as the default REPL prompt/template.
- Jenss as the primary shell script interpreter.
- Vibe interpreter for template language applications.
- Reliable output refresh on Linux without regressions on Windows.
- Safe multi-threaded and forked process handling.
- Locked-in shell syntax and parsing behavior via tests.

## Interpreter Support
- Default interpreter: jenss (first-class).
- Template interpreter: vibe (opt-in or explicit routing).
- Prompt and session template: synthetiq.
- Fixture-backed interpreter tests cover JenSS command execution, prompt flows,
  config parsing, and Automata-backed statement/feedback/language modules.

## Safety and Update Guarantees
- Red-green-refactor workflow for all behavior changes.
- Regression tests for shell syntax and interpreter contracts.
- Cross-platform IO tests for output refresh behavior.
