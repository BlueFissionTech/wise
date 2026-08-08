# Naming

Wise keeps package-owned paths short and domain-oriented so CLI, resource, and system surfaces read like low-level shell boundaries:

- `Arc` for kernel and base runtime contracts.
- `Cli` for terminal display and input components.
- `Cmd` for command parsing, handling, and suggestions.
- `IO` for input/output adapters and stream contracts.
- `Nav` for navigation, routing, and conversational adapters.
- `Res` for user-addressable resources.
- `Sys` for machine, memory, storage, and utility support.
- `Usr` for identity and profile state.

New source folders should reuse these domains before adding another top-level concept. When a new domain is necessary, prefer a short noun that can stay stable across shell, script, and agent use.

PHP class files should continue to match class names for autoload clarity. Short folder names carry the OS-style convention; class names can stay descriptive when they define public contracts.

Docs use lowercase kebab-case. Prefer short topic names for durable conventions, and use longer filenames only when the subject is a specific integration or migration contract.
