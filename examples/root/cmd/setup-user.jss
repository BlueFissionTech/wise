#!jenss

use @system, @io from system;

speak via @io: $default;

say "User profile setup.";

$userId
$userId with "User id:"?

$displayName
$displayName with "Display name:"?

$timezone
$timezone with "Timezone (e.g. UTC, PST):"?

$homeDir
$homeDir with "Home directory (relative to /usr):"?

say "Profile summary:";
say "User: ` $userId `";
say "Display: ` $displayName `";
say "Timezone: ` $timezone `";
say "Home: ` $homeDir `";
