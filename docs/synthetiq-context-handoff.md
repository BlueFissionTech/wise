# Synthetiq Context Handoff

Wise treats Synthetiq as an optional conversation and routing adapter. The package boundary is a structured handoff, not a shared ownership of Wise command execution.

Synthetiq-facing fields:

- `conversation_profile`
- `context_refs`
- `current_intent`
- `unresolved_questions`
- `confidence`
- `declared_capabilities`
- `provenance`
- `handoff_status`
- `diagnostics`
- `output_id`

Wise-owned envelope fields:

- `invocation_id`
- `session_id`
- `scope`
- `safety_policy`
- `execution_state`
- `waiting_state`
- `completed_at`
- `exit_status`

`SynthetiqContextHandoff::deterministicFixture()` provides the first side-effect-free compatibility fixture: route classification with bounded context refs, no declared external capabilities, accepted status, and a stable `output_id`.

Runtime failures should return `handoff_status=failure`, `execution_state=failed`, `exit_status=1`, and diagnostics instead of allowing optional adapter failures to crash the Wise command path.
