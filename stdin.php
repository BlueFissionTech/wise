<?php

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
