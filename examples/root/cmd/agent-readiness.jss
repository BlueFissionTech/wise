#!jenss

use @system, @io from system;

speak via @io: $default;

say "Agent readiness review.";

$objective
$objective with "Objective:"?

$resource
$resource with "Critical resource:"?

$risk
$risk with "Primary risk:"?

say "Goal: Prepare the agent environment";
say "Objective: `$objective`";
say "Resource: `$resource`";
say "Risk: `$risk`";
say "Resource claim: environment requires `$resource` for `$objective`";
say "Risk claim: review `$risk` before execution";
say "Readiness score: 1";
