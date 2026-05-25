# Wise Feature Checklist (Minimal)

- [x] Scaffold virtual root and default directories (`sys/`, `cmd/`, `usr/`, `dyn/`, `cfg/`) and lock FileSystem root.
- [x] Define system and user config overlay in JenSS (`cfg/` + `usr/<user>/cfg` overrides) with `net/`, `log/`, `fs/`, and defaults.
- [x] Add base `cmd/` executables in JenSS and define the execution syntax for scripts.
- [x] Add a dynamic resource loader plus a Vibe-based messaging resource under `sys/`.
- [x] Implement basic command input/output piping (file/stream/buffer) and tests.
- [x] Add advanced piping features after directory scaffolding is proven (batch flows, multi-output routing, fixtures).
- [ ] Add onboarding and conversational scripts in Vibe/JenSS (users, profiles, networks, dev setup, thinking prompts).
- [ ] Add prompt hints and command suggestions (context-aware, spelling, resource-aware).
- [ ] Reorder boot sequence (splash first, then progress/logs for training/config/setup, login prompt).
- [ ] Wire Synthetiq adapter, Jenss primary interpreter, and Vibe bridge (`IBridge`) with intents and routes.
- [ ] Define an ABS2/Holoscene memory adapter interface and shared access plan for Wise, Synthetiq, Jenerator, and Automata.
- [ ] Implement working memory limits and identity partitions (global vs user) with permissions.
- [ ] Align auth, roles, and permissions with Opus and allow injected auth providers.
- [ ] Emit resource output events (unique output + waiting state) with metadata and documentation.
