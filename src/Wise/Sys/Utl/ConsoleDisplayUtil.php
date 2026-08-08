<?php

namespace BlueFission\Wise\Sys\Utl;

use BlueFission\Connections\Stdio;

class ConsoleDisplayUtil {
    const COLOR_BLACK = 30;
    const COLOR_RED = 31;
    const COLOR_GREEN = 32;
    const COLOR_YELLOW = 33;
    const COLOR_BLUE = 34;
    const COLOR_MAGENTA = 35;
    const COLOR_CYAN = 36;
    const COLOR_WHITE = 37;
    const COLOR_GRAY = 90;
    const COLOR_DEFAULT = 39;

    const STYLE_BOLD = 1;
    const STYLE_UNDERLINE = 4;
    const STYLE_BLINK = 5;
    const STYLE_REVERSE = 7;
    const STYLE_HIDE = 8;

    protected static $_stdio, $_previousWidth, $_previousHeight, $_currentBuffer, $_newBuffer, $_content, $_cursorPosition;

    public static $screenHeight, $screenWidth;

    public static function init(Stdio $stdio) {
        self::$_stdio = $stdio;
        self::$_content = [];
        self::$_cursorPosition = [0, 0]; // Start cursor at the top-left
        list(self::$screenWidth, self::$screenHeight) = self::getTerminalSize();
        self::initializeBuffers(self::$_currentBuffer, self::$_newBuffer, self::$screenWidth, self::$screenHeight);

        if (function_exists('sapi_windows_vt100_support') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @sapi_windows_vt100_support(STDOUT, true);
            @sapi_windows_vt100_support(STDERR, true);
            @sapi_windows_vt100_support(STDIN, true);
        }
    }

    public static function update() {
        list(self::$screenWidth, self::$screenHeight) = self::getTerminalSize();
        self::initializeBuffers(self::$_currentBuffer, self::$_newBuffer, self::$screenWidth, self::$screenHeight);

        self::$_previousWidth = self::$screenWidth;
        self::$_previousHeight = self::$screenHeight;
    }

    public static function getTerminalSize() {
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

    public static function monitor() {
        list(self::$_currentWidth, self::$_currentHeight) = self::getTerminalSize();
        if (self::$_currentWidth !== self::$_previousWidth || self::$_currentHeight !== self::$_previousHeight) {
            // Reinitialize buffers if size has changed
            self::$screenWidth = self::$_currentWidth;
            self::$screenHeight = self::$_currentHeight;
            self::initializeBuffers(self::$_currentBuffer, self::$_newBuffer, self::$screenWidth, self::$screenHeight);
            self::$_previousWidth = self::$screenWidth;
            self::$_previousHeight = self::$screenHeight;
        }
    }

    public static function send($data) {
        return self::$_stdio->send($data);
    }

    public static function print() {
        $data = implode(PHP_EOL, self::$_content);
        self::$_stdio->send($data);
        self::$_content = [];
    }

    public static function display($data) {
        $inserts = explode(PHP_EOL, $data);

        $line = count(self::$_content) ? count(self::$_content) - 1 : 0;
        $first = true;
        foreach ($inserts as $insert) {
            if ($first == true && isset(self::$_content[$line])) {
                $insert = self::$_content[$line] . "{$insert}";
            }
            $first = false;
            self::$_content[$line] = "{$insert}";
            $line++;
        }
    }

    public static function displayLine($data) {
        $line = count(self::$_content);
        self::$_content[$line] .= $data;
    }

    public static function draw() {
        // get the last `screenHeight` number of lines of content, or all lines if it's less than
        $lines = array_slice(self::$_content, -self::$screenHeight);

        foreach ($lines as $line => $content) {
            self::updateBuffer($line, $content);
        }
        self::drawBuffer();
    }

    public static function initializeBuffers($currentBuffer = null, $newBuffer = null, $width = null, $height = null) {
        if ($width !== null) {
            self::$screenWidth = (int)$width;
        }
        if ($height !== null) {
            self::$screenHeight = (int)$height;
        }

        self::$_currentBuffer = array_fill(0, self::$screenHeight, str_repeat(' ', self::$screenWidth));
        self::$_newBuffer = array_fill(0, self::$screenHeight, str_repeat(' ', self::$screenWidth));
    }

    public static function updateBuffer($line, $content) {
        if ($line >= 0 && $line < count(self::$_newBuffer)) {
            self::$_newBuffer[$line] = self::fitLine($content, self::$screenWidth);
        }
    }

    public static function getContent() {
    	return self::$_content;
    }

    public static function flush() {
        self::$_content = [];
    }

    protected static function drawBuffer() {

        // Hide the cursor
        echo "\033[?25l";

        for ($i = 0; $i < self::$screenHeight; $i++) {
            if (self::$_newBuffer[$i] !== self::$_currentBuffer[$i]) {
                // Move the cursor to the line that needs updating
                echo "\033[" . ($i + 1) . ";1H";

                $newLine = self::$_newBuffer[$i];
                $parsedNewLine = self::parseAnsiCodes($newLine);
                $visible = $parsedNewLine['content'];
                $length = mb_strlen($visible);
                for ($j = 0; $j < $length; $j++) {
                    if (mb_substr($visible, $j, 1) !== ' ') {
                        self::$_cursorPosition = [$j + 1, $i + 1];
                    }
                }

                // Clear the line first and then print the updated line from buffer
                echo "\033[2K" . $newLine . "\033[0m";
                self::$_currentBuffer[$i] = self::$_newBuffer[$i];
            }
        }

        // Move the cursor to the bottom right after drawing
        // echo "\033[" . self::$screenHeight . ";0H";
        self::$_content = [];
    }

    public static function parseAnsiCodes($line) {
        $ansiCodePattern = '/\033\[[0-9;?]*[A-Za-z]/';
        preg_match_all($ansiCodePattern, $line, $matches, PREG_OFFSET_CAPTURE);

        $offset = 0;

        $parsedLine = [
            'content' => preg_replace($ansiCodePattern, '', $line),
            'ansiCodes' => array_reduce($matches[0], function ($carry, $match) use (&$offset) {
                $carry[$match[1] - ($offset)] = $match[0];
                $offset += strlen($match[0]);
                return $carry;
            }, [])
        ];

        return $parsedLine;
    }

    public static function fitLine(string $line, int $width): string
    {
        $width = max(0, $width);
        if ($width === 0) {
            return '';
        }

        $wrapped = self::wrapAnsi($line, $width, 1);
        $output = $wrapped[0] ?? '';
        $visibleLength = mb_strlen(self::parseAnsiCodes($output)['content']);

        if ($visibleLength < $width) {
            $output .= str_repeat(' ', $width - $visibleLength);
        }

        return $output;
    }

    public static function wrapAnsi(string $content, int $width, ?int $height = null): array
    {
        $width = max(1, $width);
        $lines = [];
        $logicalLines = preg_split('/\r\n|\r|\n/', $content);
        if (!is_array($logicalLines)) {
            $logicalLines = [$content];
        }

        foreach ($logicalLines as $logicalLine) {
            foreach (self::wrapAnsiLine($logicalLine, $width) as $line) {
                $lines[] = $line;
                if ($height !== null && count($lines) >= $height) {
                    return $lines;
                }
            }
        }

        return $lines !== [] ? $lines : [''];
    }

    private static function wrapAnsiLine(string $line, int $width): array
    {
        preg_match_all('/\033\[[0-9;?]*[A-Za-z]|./us', $line, $matches);
        $tokens = $matches[0] ?? [];
        if ($tokens === []) {
            return [''];
        }

        $lines = [];
        $output = '';
        $visibleLength = 0;

        foreach ($tokens as $token) {
            if (preg_match('/^\033\[[0-9;?]*[A-Za-z]$/', $token)) {
                $output .= $token;
                continue;
            }

            if ($visibleLength >= $width) {
                $lines[] = $output;
                $output = '';
                $visibleLength = 0;
            }

            $output .= $token;
            $visibleLength++;
        }

        $lines[] = $output;

        return $lines;
    }

    public static function colorize($data, $color) {

        return self::display("\033[{$color}m{$data}\033[0m");
    }

    public static function colorizeBackground($data, $color) {
        return self::display("\033[" . ($color + 10) . "m{$data}\033[0m");
    }

    public static function highlight($data) {
        return self::display("\033[7m{$data}\033[0m");
    }

    public static function clear() {
        self::initializeBuffers(self::$_currentBuffer, self::$_newBuffer, self::$screenWidth ?? 80, self::$screenHeight ?? 24);
        self::$_cursorPosition = [0, 0];

        return self::display("\033[2J\033[3J\033[H");
    }

    public static function clearLine() {
        return self::display("\033[2K");
    }

    public static function clearLineToBeginning() {
        return self::display("\r");
    }

    public static function clearScreen() {
        return self::clear();
    }

    public static function clearLineToEnd() {
        return self::display("\033[K");
    }

    public static function blank() {
        return self::display("\033[2J\033[1;1H");
    }

    public static function cursor($x, $y) {
        self::$_cursorPosition = [$x, $y];
        return self::display("\033[{$y};{$x}H");
    }

    public static function color($color) {
        return self::display("\033[{$color}m");
    }

    public static function reset() {
        return self::display("\033[0m");
    }

    public static function bold() {
        return self::display("\033[1m");
    }

    public static function underline() {
        return self::display("\033[4m");
    }

    public static function blink() {
        return self::display("\033[5m");
    }

    public static function reverse() {
        return self::display("\033[7m");
    }

    public static function hide() {
        return self::display("\033[8m");
    }

    public static function show() {
        return self::display("\033[28m");
    }
}
