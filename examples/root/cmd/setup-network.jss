#!jenss

use @system, @io from system;

speak via @io: $default;

say "Network setup.";

$allowedPorts
$allowedPorts with "Allowed ports (comma list):"?

$blockedPorts
$blockedPorts with "Blocked ports (comma list):"?

$dnsServers
$dnsServers with "DNS servers (comma list):"?

$proxy
$proxy with "Proxy (blank if none):"?

say "Network summary:";
say "Allow: ` $allowedPorts `";
say "Block: ` $blockedPorts `";
say "DNS: ` $dnsServers `";
say "Proxy: ` $proxy `";
