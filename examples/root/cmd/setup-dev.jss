#!jenss

use @system, @io from system;

speak via @io: $default;

say "Development environment setup.";

$languages
$languages with "Languages (comma list):"?

$tools
$tools with "Primary tools (comma list):"?

$repoRoot
$repoRoot with "Repo root path:"?

$workflow
$workflow with "Workflow focus (build, test, deploy, research):"?

say "Development summary:";
say "Languages: ` $languages `";
say "Tools: ` $tools `";
say "Repo root: ` $repoRoot `";
say "Workflow: ` $workflow `";
