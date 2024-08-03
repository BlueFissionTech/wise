<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait Glitches {
	protected function glitch($data, $args = null)
    {
        $lines = explode(PHP_EOL, $data);
        $colors = ['red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'magenta' => 35, 'cyan' => 36, 'white' => 37];
        $ascii = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '+', '=', '[', ']', '{', '}', '|', '\\', '/', '<', '>', '?', ',', '.', ':', ';', '"', "'"];

        if ($args && isset($args['randomcolor'])) {
            foreach ($lines as $offset => $line) {
                $lines[$offset] = "\033[{$colors[array_rand($colors)]}m{$line}\033[0m";
            }
        }

        if ($args && isset($args['corrupt'])) {
            foreach ($lines as $offset => $line) {
                $chars = str_split($line);
                $charCount = count($chars);
                $charCount = $charCount > 3 ? 3 : $charCount;
                if (!is_array($chars) || count($chars) <= 0) continue;
                $randChars = array_rand($chars, $charCount);
                if ($randChars == 0) continue;
                foreach ($randChars as $randChar) {
                    $color = $colors[array_rand($colors)];
                    $data = $ascii[array_rand($ascii)];
                    $chars[$randChar] = "\033[{$color}m{$data}\033[0m";
                }
                $lines[$offset] = implode('', $chars);
            }
        }

        if ($args && isset($args['jumble'])) {
            foreach ($lines as $offset => $line) {
                $chars = str_split($line);
                shuffle($chars);
                $lines[$offset] = implode('', $chars);
            }
        }

        if ($args && isset($args['jitter'])) {
            foreach ($lines as $offset => $line) {
                $inOrOut = rand(0, 1);
                if ($inOrOut == 1) {
                    $line = " " . $line;
                } elseif (strpos($line, ' ') !== false) {
                    $line = substr($line, 1);
                }
                $lines[$offset] = $line;
            }
        }

        if ($args && isset($args['random'])) {
            for ($i = 0; $i < count($lines); $i++) {
                $line = $lines[$i];
                $glitch = '';
                for ($j = 0; $j < strlen($line); $j++) {
                    $glitch .= chr(rand(33, 126));
                }
                $lines[$i] = $glitch;
            }
        }

        $this->_content->val(implode(PHP_EOL, $lines));
    }
}