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

## Issue #14 Slice: Runtime Storage And Identity Guards

This slice expands helper coverage in file-backed runtime paths and user identity/profile surfaces:

- `DirectoryManager::ensurePath()` centralizes nested directory creation through the Wise DevElation-backed filesystem adapter.
- Storage-backed resources now use `DirectoryManager::ensurePath()` instead of raw `is_dir()`, `file_exists()`, and `mkdir()` guards.
- `Identity` and `Profile` now use `Val`, `Str`, and `Arr` for presence, empty, array, and membership checks.
- `StepResource::perform()` now uses an `Arr` object for queue-style argument shifting instead of raw `array_shift()`.
- `ScriptedResourceRegistry` now uses `DirectoryManager`, `Arr`, `Str`, and `Val` for runtime directory and duplicate-registration guards.

Native calls intentionally retained in this slice:

- `glob()` remains the filesystem enumeration boundary for Vibe resource discovery until a DevElation file enumeration API is selected for this use case.
- `dirname()` and `basename()` remain path-boundary helpers inside `DirectoryManager::ensurePath()` while creation itself goes through `FileSystem`.
- Existing JSON encode/decode, ranking, and external API response parsing remain outside this slice and should be audited separately.
