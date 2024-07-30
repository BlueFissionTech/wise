<?php

// Poll STDIN for input and write it to a transient IPC memory buffer that can be read from a stream in another application
while (true) {
	$input = fgetc(STDIN);
	
	if ($input) {
		// file_put_contents('php://memory', $input);
		echo $input;
		echo "blafsjdf";
	}
}
