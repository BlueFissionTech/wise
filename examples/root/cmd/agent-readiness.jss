#!jenss

use @system, @io from system;
use @statement from intelligence.statement;
use @feedback from intelligence.feedback;

speak via @io: $default;

say "Agent readiness review.";

$objective
$objective with "Objective:"?

$resource
$resource with "Critical resource:"?

$risk
$risk with "Primary risk:"?

@resourceClaim
set @resourceClaim to @statement: /make "environment", "requires", $resource, "must", "agent";
@resourceClaim: /source "wise:agent-readiness";
@resourceClaim: /confidence 0.86;
@resourceClaim: /evidence ["objective", "resource"];

@riskClaim
set @riskClaim to @statement: /make "risk", "may_block", $risk, "should", "review";
@riskClaim: /source "wise:agent-readiness";
@riskClaim: /confidence 0.72;
@riskClaim: /evidence ["objective", "risk"];

@signals
set @signals to @feedback: /make;
@signals: /positive "readiness:objective-captured", 1;
@signals: /positive "readiness:resource-declared", 1;
@signals: /positive "readiness:risk-declared", 1;

$resourceSummary
set $resourceSummary to @resourceClaim: /explain;

$riskSummary
set $riskSummary to @riskClaim: /explain;

$readinessScore
set $readinessScore to @signals: /score "readiness:resource-declared";

say "Goal: Prepare the agent environment";
say "Objective: `$objective`";
say "Resource: `$resource`";
say "Risk: `$risk`";
say "Resource claim: `$resourceSummary`";
say "Risk claim: `$riskSummary`";
say "Readiness score: `$readinessScore`";
