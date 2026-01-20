# Wise Shell Roadmap

## Guiding Principles
- Test-first changes to lock behavior before refactors.
- Cross-platform parity (Linux and Windows).
- Clear interpreter contracts and minimal breakage.
- Blue Fission conventions (DevElation types, events, hooks).

## Phase 0: Documentation Baseline
- SPEC.md, ARCHITECTURE.md, FEATURES.md, ROADMAP.md established and updated.

## Phase 1: Test Baseline (red)
- Shell syntax parsing tests (golden cases + edge cases).
- Command routing and resource execution tests.
- Input/output piping tests (file, stream, buffer).
- Output refresh behavior tests (Linux and Windows).
- Process management tests (spawn, wait, cancel, forked flows).

## Phase 2: Virtual Root + Config (red/green)
- Scaffold `sys/`, `cmd/`, `usr/`, `dyn/`, `cfg/` and lock FileSystem root.
- Define system and user config overlay in JenSS with `net/`, `etc/`, firewall defaults.
- Seed minimal config files and document precedence.

## Phase 3: Core Scripts + Resources (green)
- Add base JenSS executables in `cmd/` with defined execution syntax.
- Add dynamic resource loader and Vibe messaging resource in `sys/`.
- Add onboarding and conversational scripts in Vibe/JenSS.

## Phase 4: Interpreter Alignment (green)
- Wire Synthetiq adapter as the canonical prompt/template.
- Set JenSS as the default interpreter.
- Integrate Vibe via `IBridge` and route via intents.

## Phase 5: UX and Output Events (refactor)
- Reorder boot sequence (splash, progress/logs, training/config, login).
- Add prompt hints and command suggestions (context-aware, resource-aware).
- Emit resource output events for unique output and waiting state with metadata.

## Phase 6: Memory and Identity (refactor)
- Define ABS2/Holoscene memory adapter interface.
- Implement working memory limits and global/user partitions.
- Add identity and profile management hooks with permissions.

## Phase 7: Auth, Roles, and Permissions (refactor)
- Align with Opus auth patterns and allow injected providers.
- Add role and permission checks compatible with Opus controllers.

## Full Execution Checklist
- [ ] Confirm scope and acceptance criteria in SPEC.md.
- [ ] Inventory current parser behavior with focused tests.
- [ ] Add golden tests for shell syntax (quoting, escapes, pipes, redirects).
- [ ] Add error-behavior tests for invalid syntax and recovery.
- [ ] Add tests for resource routing and action dispatch.
- [ ] Add tests for input/output piping (file, stream, buffer).
- [ ] Scaffold `sys/`, `cmd/`, `usr/`, `dyn/`, `cfg/` and lock FileSystem root.
- [ ] Define JenSS config overlay (`cfg/` and `usr/<user>/cfg`) and seed defaults.
- [ ] Add JenSS executables in `cmd/` with defined execution syntax.
- [ ] Add dynamic resource loader and Vibe messaging resource in `sys/`.
- [ ] Add onboarding and conversational scripts in Vibe/JenSS.
- [ ] Add interpreter contract tests (JenSS + Vibe) using fixtures.
- [ ] Wire Synthetiq adapter and set JenSS as default interpreter.
- [ ] Integrate Vibe via `IBridge` and route via intents.
- [ ] Add prompt hints and command suggestions.
- [ ] Reorder boot sequence (splash, progress/logs, training/config, login).
- [ ] Emit resource output events (unique output + waiting state) with metadata.
- [ ] Define ABS2/Holoscene memory adapter interface and shared access plan.
- [ ] Implement working memory limits and global/user partitions.
- [ ] Align auth, roles, and permissions with Opus and allow injected providers.
- [ ] Review regressions and lock behavior in tests.
