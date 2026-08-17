<?php

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use BlueFission\Arr;
use BlueFission\Data\Storage\Memory;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Cmd\CommandRequest;

$arguments = Arr::make($argv)->slice(1);
$input = $arguments->join(' ')->trim();
$input = Str::isNotEmpty($input->val()) ? $input->val() : 'list file';

$processor = new CommandProcessor(new Memory());
$result = $processor->process(CommandRequest::parse(
    $input,
    ['source' => 'headless-example']
));

echo HTTP::jsonEncode($result->toArray()) . PHP_EOL;
