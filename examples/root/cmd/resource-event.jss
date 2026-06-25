#!jenss

use @system, @io from system;

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

say "Resource event envelope.";
say "Output id: `$outputId`";
say "Resource: `$resourceName`";
say "Action: `$action`";
say "Status: `$status`";
say "Prompt state: `$promptState`";
say "Waiting state: `$waitingState`";
say "Semantic metadata: source=wise:resource-event; evidence=output_id,resource,status,waiting_state";
