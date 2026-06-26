# DevElation Helper Coverage

## Issue #14 Slice: Command Suggestions

This slice audits `src/Wise/Cmd/CommandSuggester.php` and converts high-confidence primitive handling to DevElation helpers:

- String normalization now uses `Str` for trim/lower/empty checks.
- Collection checks, key checks, slicing, filtering, mapping, uniquing, and counting now use `Arr`.
- Value guards now use `Val` where nullability or existence is the actual concern.

Native calls intentionally retained in this file:

- `preg_split` remains the tokenization boundary for whitespace parsing.
- `similar_text` and `arsort` remain the ranking algorithm primitives because there is no equivalent DevElation ranking helper in this package surface.
- `implode` remains the response formatting boundary for joined suggestion text.

Next likely audit targets are `CommandProcessor`, `CommandParser`, `Cli\Console`, and legacy resource classes with file-system or collection primitives.
