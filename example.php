<?php
function getTerminalSize() {
    if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
        $output = [];
        $return_var = 0;
        exec('powershell -command "echo (Get-Host).UI.RawUI.WindowSize.Width; echo (Get-Host).UI.RawUI.WindowSize.Height"', $output, $return_var);
        if ($return_var == 0 && count($output) >= 2) {
            $cols = (int)$output[0];
            $rows = (int)$output[1];
            return [$cols, $rows];
        }
    } else {
        // Unix-like systems
        $output = [];
        $return_var = 0;
        exec('stty size 2>&1', $output, $return_var);
        if ($return_var == 0 && count($output) > 0) {
            list($rows, $cols) = explode(' ', $output[0]);
            return [(int)$cols, (int)$rows];
        }
    }
    return [80, 24]; // Fallback to default size
}

function clearScreen() {
    echo "\033[H\033[J";
    global $screenHeight, $screenWidth;
    for ($i = 0; $i < $screenHeight; $i++) {
        echo "\r" . str_repeat(' ', $screenWidth) . "\r"; // Clear line
    }
    echo "\033[H"; // Move cursor to the top left again
}

function updateBuffer(&$buffer, $line, $content) {
    global $screenWidth;
    if ($line >= 0 && $line < count($buffer)) {
        $buffer[$line] = str_pad(substr($content, 0, $screenWidth), $screenWidth);
    }
}

function drawBuffer($newBuffer, &$currentBuffer) {
    global $screenHeight, $screenWidth;

    for ($i = 0; $i < $screenHeight; $i++) {
        if ($newBuffer[$i] !== $currentBuffer[$i]) {
            // Move the cursor to the line that needs updating
            echo "\033[" . ($i + 1) . "H";
            for ($j = 0; $j < $screenWidth; $j++) {
                if ($newBuffer[$i][$j] !== $currentBuffer[$i][$j]) {
                    // Move the cursor to the specific character position
                    echo "\033[" . ($i + 1) . ";" . ($j + 1) . "H";
                    echo $newBuffer[$i][$j];
                    // Update the current buffer character
                    $currentBuffer[$i][$j] = $newBuffer[$i][$j];
                }
            }
        }
    }
    // Move the cursor to the bottom right after drawing
    echo "\033[" . $screenHeight . ";0H";
}

function initializeBuffers(&$currentBuffer, &$newBuffer, $width, $height) {
    $currentBuffer = array_fill(0, $height, str_repeat(' ', $width));
    $newBuffer = array_fill(0, $height, str_repeat(' ', $width));
}

// Initial terminal size
list($screenWidth, $screenHeight) = getTerminalSize();
initializeBuffers($currentBuffer, $newBuffer, $screenWidth, $screenHeight);

$previousWidth = $screenWidth;
$previousHeight = $screenHeight;

clearScreen();

// Example usage
for ($i = 0; $i <= 100; $i++) {
    // Check for terminal size change
    list($currentWidth, $currentHeight) = getTerminalSize();
    if ($currentWidth !== $previousWidth || $currentHeight !== $previousHeight) {
        // Reinitialize buffers if size has changed
        $screenWidth = $currentWidth;
        $screenHeight = $currentHeight;
        initializeBuffers($currentBuffer, $newBuffer, $screenWidth, $screenHeight);
        $previousWidth = $screenWidth;
        $previousHeight = $screenHeight;
    }

    $output = "
            ██╗    ██╗██╗███████╗███████╗
            ██║    ██║██║██╔════╝██╔════╝
            ██║ █╗ ██║██║███████╗█████╗  
            ██║███╗██║██║╚════██║██╔══╝  
            ╚███╔███╔╝██║███████║███████╗
             ╚══╝╚══╝ ╚═╝╚══════╝╚══════╝
            ";

    $output = "
    Hi,
    My name is 'Script' and I'm here to help.

    How are you?
    ";
    foreach (explode(PHP_EOL, $output) as $line=>$data) {
        updateBuffer($newBuffer, $line, $data);
    }

    // updateBuffer($newBuffer, 0, $output);
    drawBuffer($newBuffer, $currentBuffer);
    usleep(100000); // Sleep for 0.1 second
}
?>
