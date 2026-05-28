#!jenss

use @system, @io from system;
use @markov from intelligence.language;

speak via @io: $default;

say "Command suggestion training.";

$history
set $history to [
    "list all resources",
    "list command resources",
    "show memory status",
    "show resource events",
    "run agent readiness"
];

for each $line in $history,
    @markov: /addSentence $line;

$suggestion
set $suggestion to @markov: /predictNextWord "list";

say "Seeded command history: 5";

if $suggestion ? is below 0.5 then
    say "Suggestion: no strong next token.";

if $suggestion ? is 0.5 or above then
    say "Suggestion: `$suggestion`";
