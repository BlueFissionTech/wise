# Wise Shell Roadmap

## Guiding Principles
- Test-first changes to lock behavior before refactors.
- Cross-platform parity (Linux and Windows).
- Clear interpreter contracts and minimal breakage.

## Phase 0: Documentation Baseline (now)
- SPEC.md, ARCHITECTURE.md, FEATURES.md, ROADMAP.md established.

## Phase 1: Test Baseline (red)
- Shell syntax parsing tests (golden cases + edge cases).
- Command routing and resource execution tests.
- Interpreter contract tests (inputs/outputs, error semantics).
- Output refresh behavior tests (Linux and Windows).
- Process management tests (spawn, wait, cancel, forked flows).

## Phase 2: Interpreter Refactors (green)
- Adopt synthetiq as the core REPL prompt/template.
- Set jenss as the default interpreter.
- Integrate vibe for template language apps.

## Phase 3: Platform Output Fixes (refactor)
- Diagnose Linux output refresh failures.
- Validate no regressions on Windows.
- Add regression tests for terminal redraw and buffering.

## Phase 4: Concurrency and Forking
- Harden multi-threaded process orchestration.
- Add tests for cancellation, cleanup, and output ordering.

## Full Execution Checklist
- [ ] Confirm scope and acceptance criteria in SPEC.md.
- [ ] Inventory current parser behavior with focused tests.
- [ ] Add golden tests for shell syntax (quoting, escapes, pipes, redirects).
- [ ] Add error-behavior tests for invalid syntax and recovery.
- [ ] Add tests for resource routing and action dispatch.
- [ ] Add interpreter contract tests (jenss + vibe) using fixtures.
- [ ] Add tests for output refresh behavior (Linux + Windows).
- [ ] Add tests for ProcessManager concurrency and forked flows.
- [ ] Wire synthetiq as default REPL prompt with config switch.
- [ ] Set jenss as default interpreter and preserve fallbacks.
- [ ] Integrate vibe for template apps with explicit routing.
- [ ] Fix Linux output refresh; verify Windows stability.
- [ ] Refactor process handling with test-backed safety.
- [ ] Review regressions and lock behavior in tests.
