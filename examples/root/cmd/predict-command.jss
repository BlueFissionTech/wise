#!jenss

use @system, @io from system;
use @json from std.data;

speak via @io: $default;

say "Command suggestion training.";

$history
set $history to @json: /parse "examples/root/data/command-history.json";

$suggestion
set $suggestion to "list command resources";

say "Seeded command history: 5";
say "Suggestion: `$suggestion`";
