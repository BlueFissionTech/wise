<?php

require __DIR__ . '/vendor/autoload.php';

use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;

$argv = $_SERVER['argv'] ?? [];
$argParser = new Args(['allowUnknown' => true, 'autoHelp' => true]);
$argParser->addOptions([
    new OptionDefinition('test', [
        'type' => 'bool',
        'env' => 'WISE_POLLING_TEST',
        'description' => 'Emit a single test line and exit.',
    ]),
    new OptionDefinition('enabled', [
        'type' => 'bool',
        'env' => 'WISE_POLLING',
        'description' => 'Enable background polling output.',
    ]),
    new OptionDefinition('interval', [
        'type' => 'int',
        'env' => 'WISE_POLLING_INTERVAL',
        'description' => 'Polling interval in seconds.',
    ]),
]);
$argParser->parse($argv);
$options = $argParser->options();
if (!empty($options['help'])) {
    echo $argParser->usage($argv[0] ?? 'polling.php') . PHP_EOL;
    exit(0);
}
if (array_key_exists('test', $options)) {
    putenv('WISE_POLLING_TEST=' . ($options['test'] ? '1' : '0'));
}
if (array_key_exists('enabled', $options)) {
    putenv('WISE_POLLING=' . ($options['enabled'] ? '1' : '0'));
}
if (array_key_exists('interval', $options)) {
    putenv('WISE_POLLING_INTERVAL=' . (string)$options['interval']);
}

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
