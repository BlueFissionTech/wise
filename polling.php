<?php
// Simulate periodic output
while (true) {
    echo "Background process output at " . date('H:i:s') . "\n";
    sleep(2); // Sleep for 2 seconds before generating the next output
}