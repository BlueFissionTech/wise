#!jenss

use @system, @io from system;

speak via @io: $default;

say "Permissions setup.";

$role
$role with "Role (admin, dev, ops, user):"?

$canWrite
$canWrite with "Allow write actions? (yes/no):"?

$canExecute
$canExecute with "Allow script execution? (yes/no):"?

say "Permissions summary:";
say "Role: ` $role `";
say "Write: ` $canWrite `";
say "Execute: ` $canExecute `";

if $role is "admin" then
	say "Reminder: admin permissions should be used sparingly.";
