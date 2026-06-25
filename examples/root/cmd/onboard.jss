#!jenss

use @system, @io from system;

speak via @io: $default;

say "Wise onboarding begins.";

$userId
$userId with "Enter user id:"?

$role
$role with "Role (admin, dev, ops, user):"?

$focus
$focus with "Focus (engineering, administration, productivity, creativity):"?

$goal
$goal with "Primary goal for this session:"?

say "Summary:";
say "User: ` $userId `";
say "Role: ` $role `";
say "Focus: ` $focus `";
say "Goal: ` $goal `";

say "Next steps:";
say "- run `setup-user` for profile details";
say "- run `setup-profile` for chatbot persona";
say "- run `setup-network` for connectivity";
say "- run `setup-dev` for development tools";
say "- run `setup-permissions` for access";
