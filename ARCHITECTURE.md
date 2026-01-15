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
