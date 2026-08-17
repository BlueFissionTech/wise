# DevElation Usage

Wise treats DevElation primitives as runtime values, not only as aliases for PHP functions.

## Preferred Boundaries

- Use `Str` for command text, paths, identifiers, and output that is normalized or mutated.
- Use `Arr` for arguments, queues, result collections, and maps that are filtered, reshaped, or mutated.
- Use `Val` for optional values and presence/null checks.
- Use `Date` for timestamps carried in command, resource, or event payloads.
- Return raw scalars and arrays only at public interoperability boundaries.

## Native PHP Exceptions

Native functions remain appropriate where DevElation has no equivalent contract or PHP itself defines the interoperability boundary. Current examples include callable invocation, extension/class availability checks, stream resource checks, JSON encoding flags, and exact filesystem permission predicates.

## Audit Scope

The issue #14 audit is being advanced in bounded lifecycle slices. The scripted-resource and bridge-registry paths now keep normalization, collection shaping, optional-value checks, timestamps, and queue-style mutation in DevElation primitives. Remaining resource conversions should preserve behavior with focused tests instead of applying mechanical replacements across unrelated legacy classes.
