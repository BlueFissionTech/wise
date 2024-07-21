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
        foreach (self::$_content as $line => $content) {
            self::updateBuffer($line, $content);
        }
        self::drawBuffer();
    }

    public static function initializeBuffers() {
        self::$_currentBuffer = array_fill(0, self::$screenHeight, str_repeat(' ', self::$screenWidth));
        self::$_newBuffer = array_fill(0, self::$screenHeight, str_repeat(' ', self::$screenWidth));
    }

    public static function updateBuffer($line, $content) {
        if ($line >= 0 && $line < count(self::$_newBuffer)) {
            self::$_newBuffer[$line] = str_pad(mb_substr($content, 0, self::$screenWidth), self::$screenWidth);
        }
    }

    protected static function drawBuffer() {
        $prevAnsiState = '';

        // Hide the cursor
        echo "\033[?25l";

        for ($i = 0; $i < self::$screenHeight; $i++) {
            if (self::$_newBuffer[$i] !== self::$_currentBuffer[$i]) {
                // Move the cursor to the line that needs updating
                echo "\033[" . ($i + 1) . ";1H";

                $currentLine = self::$_currentBuffer[$i];
                $newLine = self::$_newBuffer[$i];

                $parsedNewLine = self::parseAnsiCodes($newLine);
                $parsedCurrentLine = self::parseAnsiCodes($currentLine);

                $length = max(mb_strlen($parsedNewLine['content']), mb_strlen($parsedCurrentLine['content']));

                $lineBuffer = '';

                for ($j = 0; $j < $length; $j++) {
                    if ($j >= mb_strlen($parsedCurrentLine['content']) || $j >= mb_strlen($parsedNewLine['content']) || (mb_substr($parsedCurrentLine['content'], $j, 1) !== mb_substr($parsedNewLine['content'], $j, 1) || mb_substr($parsedNewLine['content'], $j, 1) == ' ')) {
                        // If there's an ANSI code to apply, apply it
                        if (isset($parsedNewLine['ansiCodes'][$j])) {
                            $lineBuffer .= $parsedNewLine['ansiCodes'][$j];
                            $prevAnsiState = $parsedNewLine['ansiCodes'][$j];
                        } else {
                            // Apply the previous line's ANSI state if no new code
                            $lineBuffer .= $prevAnsiState;
                        }

                        // Append the character to the line buffer
                        $lineBuffer .= mb_substr($parsedNewLine['content'], $j, 1) ?? ' ';
                    } else {
                        // Preserve the character if it's the same
                        $lineBuffer .= mb_substr($parsedCurrentLine['content'], $j, 1);
                    }

                    // Update the cursor position
                    if (mb_substr($parsedNewLine['content'], $j, 1) !== ' ') {
                        self::$_cursorPosition = [$j + 1, $i + 1];
                    }
                }

                // Clear the line first and then print the updated line from buffer
                echo "\033[2K" . $lineBuffer . "\033[0m";
                self::$_currentBuffer[$i] = self::$_newBuffer[$i];
            }
        }


        // foreach (self::$_newBuffer as $line => $content) {
		// 	if ($line <= self::$screenHeight) {
		// 		fputs($stream, $content);
		// 	}
		// }

        // die('testing mmode');

        // Draw the custom cursor at the end of the drawn content
        self::drawCustomCursor();

        // Move the cursor to the bottom right after drawing
        echo "\033[" . self::$screenHeight . ";0H";
        self::$_content = [];
    }

    public static function drawCustomCursor() {
        $cursorSymbol = '_'; // Custom cursor symbol

        list($x, $y) = self::$_cursorPosition;

        // Draw the custom cursor
        echo "\033[" . $y . ";" . $x . "H" . $cursorSymbol;
    }

    public static function parseAnsiCodes($line) {
        $ansiCodePattern = '/\033\[[0-9;]*m/';
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
        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            system('cls');
        } else {
            system('clear');
        }

        return self::display("\033[2J\033[1;1H");
    }

    public static function clearLine() {
        return self::display("\033[2K");
    }

    public static function clearLineToBeginning() {
        return self::display("\r");
    }

    public static function clearScreen() {
        self::display("\033[H\033[J");

        for ($i = 0; $i < self::$screenHeight; $i++) {
            self::display("\r" . str_repeat(' ', self::$screenWidth) . "\r"); // Clear line
        }

        self::display("\033[H"); // Move cursor to the top left again
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