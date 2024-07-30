<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Arr;

class SplashScreen extends Component
{
    use Traits\Collides;
    use Traits\CanMove;

    protected string $_splashData;
    protected static int $_lastGlitch = 0;
    protected $_firstDisplayTime;

    public function __construct(int $x = 0, int $y = 0, int $width = 80, int $height = 12, int $zIndex = 0)
    {
        parent::__construct($x, $y, $width, $height, '', $zIndex);
        $this->splashData();
        $this->splash();
    }

    protected function splashData()
    {
        $this->_splashData = "
            ██╗    ██╗██╗███████╗███████╗
            ██║    ██║██║██╔════╝██╔════╝
            ██║ █╗ ██║██║███████╗█████╗  
            ██║███╗██║██║╚════██║██╔══╝  
            ╚███╔███╔╝██║███████║███████╗
             ╚══╝╚══╝ ╚═╝╚══════╝╚══════╝";
    }

    public function update(): void
    {
        $this->splash();
    }

    public function draw(): array
    {
        // After 10 seconds, no longer require redrawing the splashcreen
        if ( !$this->_firstDisplayTime ) {
            $this->_firstDisplayTime = time();
        } elseif ( (time() - $this->_firstDisplayTime) > 10 ) {
            $this->_needsRedraw = false;
        }

        return explode(PHP_EOL, $this->_content);
    }

    public function splash()
    {
        $effects = ['randomcolor', 'jumble', 'jitter', 'corrupt'];
        $glitchInterval = random_int(3, 7);
        $effect = $effects[array_rand($effects)];

        if (self::$_lastGlitch == 0 || (time() - self::$_lastGlitch) > $glitchInterval) {
            $effect = $effects[array_rand($effects)];
            $this->glitch($this->_splashData, [$effect => true]);
            self::$_lastGlitch = time();
        } else {
            $splash = explode(PHP_EOL, $this->_splashData);
            foreach ($splash as $line => $data) {
                $splash[$line] = "\033[37m{$data}\033[0m";
            }
            $this->_content = implode(PHP_EOL, $splash);
        }

        $this->_content .= PHP_EOL . "            ";
        $this->_content .= "Workspace Intelligence Shell Environment\033[90m\033[0m";
        $this->_content .= PHP_EOL . PHP_EOL;
        $this->_content .= "Running WISE version 0.0.1, produced by \033[36mBlue Fission\033[0m.";
        $this->_content .= PHP_EOL;
        $this->_content .= "\033[32mJen\033[0m interpreter running version 0.0.1." . PHP_EOL . PHP_EOL;
    }

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

        $this->_content = implode(PHP_EOL, $lines);
    }
}
