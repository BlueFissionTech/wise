# Command Parsing

Wise text commands use the form `<verb> <resource> [arguments]`.

## Resource identifiers

Unquoted resource identifiers may contain lowercase letters, digits, and single
word separators. Hyphens are canonical. Underscores are accepted as input
aliases and normalize to hyphens when no registered resource defines a more
specific canonical name.

Examples:

- `list missing-resource` resolves the resource as `missing-resource`.
- `list missing_resource` resolves the resource as `missing-resource`.
- If a registered service uses `missing_resource`, either spelling resolves to
  that registered service name so dispatch remains compatible.

The first valid unquoted separated identifier after a verb is treated as the
resource when no known resource has already been recognized. Plain unknown
words remain arguments so native commands keep their established behavior.
Later tokens remain arguments. Quoted values are always arguments. Structured
command payloads are not text normalized; their resource identifiers remain
exactly as supplied by the caller.
