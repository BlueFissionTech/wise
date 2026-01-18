<?php

$testMode = getenv('WISE_POLLING_TEST') === '1';
$enabled = getenv('WISE_POLLING') === '1';
$interval = (int)(getenv('WISE_POLLING_INTERVAL') ?: 2);
$interval = $interval > 0 ? $interval : 2;

if ($testMode) {
    echo "Background process output at " . date('H:i:s') . "\n";
    exit(0);
}

// Simulate periodic output only when enabled.
if (!$enabled) {
    while (true) {
        sleep($interval);
    }
}

while (true) {
    echo "Background process output at " . date('H:i:s') . "\n";
    sleep($interval); // Sleep for 2 seconds before generating the next output
}
