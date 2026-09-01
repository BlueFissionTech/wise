<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Arr;
use BlueFission\Wise\Version;

class SplashScreen extends Component
{
    use Traits\Collides;
    use Traits\CanMove;
    use Traits\Glitches;

    protected string $_splashData;
    protected bool $_asciiSplash = false;
    protected bool $_glitchEnabled = false;
    protected static int $_lastGlitch = 0;
    protected $_firstDisplayTime;

    public function __construct(int $x = 0, int $y = 0, int $width = 80, int $height = 12, int $zIndex = 0)
    {
        parent::__construct($x, $y, $width, $height, '', $zIndex);
        $glitch = getenv('WISE_SPLASH_GLITCH');
        $this->_glitchEnabled = $glitch === false
            ? PHP_OS !== 'WINNT'
            : filter_var($glitch, FILTER_VALIDATE_BOOLEAN);
        $this->splashData();
        $this->splash();
    }

    protected function splashData()
    {
        $style = strtolower((string)getenv('WISE_SPLASH_STYLE'));
        $useAscii = $style === 'ascii' || (PHP_OS === 'WINNT' && $style !== 'full');
        $this->_asciiSplash = $useAscii;

 //        if ($useAscii) {
 //            $this->_splashData = "
 // __        ___ ____  _____
 // \\ \\      / / |  _ \\| ____|
 //  \\ \\ /\\ / /| | |_) |  _|
 //   \\ V  V / | |  __/| |___
 //    \\_/\\_/  |_|_|   |_____|";
 //            return;
 //        }

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
        if ($this->_glitchEnabled) {
            $this->splash();
            if (!$this->_firstDisplayTime || (time() - $this->_firstDisplayTime) <= 60) {
                $this->_needsRedraw = true;
            }
        }
    }

    public function draw(): array
    {
        // After 10 seconds, no longer require redrawing the splashcreen
        if ( !$this->_firstDisplayTime ) {
            $this->_firstDisplayTime = time();
        } elseif ( (time() - $this->_firstDisplayTime) > 60 || !$this->_glitchEnabled ) {
            $this->_needsRedraw = false;
        }

        return preg_split("/\r?\n/", $this->_content->val());
    }

    public function splash()
    {
        $content = '';

        if ($this->_glitchEnabled) {
            $effects = ['randomcolor', 'jumble', 'jitter', 'corrupt'];
            $glitchInterval = random_int(3, 7);
            if (self::$_lastGlitch == 0 || (time() - self::$_lastGlitch) > $glitchInterval) {
                $effect = $effects[array_rand($effects)];
                $content = $this->glitch($this->_splashData, [$effect => true]);
                self::$_lastGlitch = time();
            } else {
                $splash = preg_split("/\r?\n/", $this->_splashData);
                foreach ($splash as $line => $data) {
                    $splash[$line] = "\033[37m{$data}\033[0m";
                }
                $content = implode(PHP_EOL, $splash);
            }
        } else {
            $splash = preg_split("/\r?\n/", $this->_splashData);
            $content = implode(PHP_EOL, $splash);
        }

        $content .= PHP_EOL . "            ";
        $content .= "Workspace Intelligence Shell Environment";
        $content .= PHP_EOL . PHP_EOL;
        $content .= 'Running WISE version ' . Version::CURRENT . ', produced by Blue Fission.';
        $content .= PHP_EOL;
        $content .= 'Jen interpreter running version '
            . Version::package('bluefission/jenerator')
            . '.' . PHP_EOL . PHP_EOL;

        $this->setContent($content);
    }
    
}
