<?php

require __DIR__ . '/vendor/autoload.php';

use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;

$argv = $_SERVER['argv'] ?? [];
$argParser = new Args(['allowUnknown' => true, 'autoHelp' => true]);
$argParser->addOptions([
    new OptionDefinition('test', [
        'type' => 'bool',
        'env' => 'WISE_STDIN_TEST',
        'description' => 'Read a single character and exit.',
    ]),
]);
$argParser->parse($argv);
$options = $argParser->options();
if (!empty($options['help'])) {
    echo $argParser->usage($argv[0] ?? 'stdin.php') . PHP_EOL;
    exit(0);
}
if (array_key_exists('test', $options)) {
    putenv('WISE_STDIN_TEST=' . ($options['test'] ? '1' : '0'));
}

$testMode = getenv('WISE_STDIN_TEST') === '1';

if ($testMode) {
    $input = fgetc(STDIN);
    if ($input !== false) {
        echo $input;
    }
    exit(0);
}

// Poll STDIN for input and write it to a transient IPC memory buffer that can be read from a stream in another application
while (true) {
	$input = fgetc(STDIN);
	
	if ($input !== false && $input !== '') {
		echo $input;
	}
}
