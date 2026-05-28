#!jenss

use @system, @io from system;
use @statement from intelligence.statement;

speak via @io: $default;

$outputId
set $outputId to "out-001";

$resourceName
set $resourceName to "command";

$action
set $action to "run";

$status
set $status to "waiting";

$promptState
set $promptState to "suspended";

$waitingState
set $waitingState to "waiting_for_output";

@eventClaim
set @eventClaim to @statement: /make "resource output", "reports", $status, "must", "wise_event";
@eventClaim: /source "wise:resource-event";
@eventClaim: /confidence 0.88;
@eventClaim: /evidence ["output_id", "resource", "status", "waiting_state"];

$eventSummary
set $eventSummary to @eventClaim: /explain;

say "Resource event envelope.";
say "Output id: `$outputId`";
say "Resource: `$resourceName`";
say "Action: `$action`";
say "Status: `$status`";
say "Prompt state: `$promptState`";
say "Waiting state: `$waitingState`";
say "Semantic metadata: `$eventSummary`";
