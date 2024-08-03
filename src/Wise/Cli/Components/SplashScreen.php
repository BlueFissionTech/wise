<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Arr;

class SplashScreen extends Component
{
    use Traits\Collides;
    use Traits\CanMove;
    use Traits\Glitches;

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
        } elseif ( (time() - $this->_firstDisplayTime) > 60 ) {
            $this->_needsRedraw = false;
        }

        return explode(PHP_EOL, $this->_content->val());
    }

    public function splash()
    {
        $effects = ['randomcolor', 'jumble', 'jitter', 'corrupt'];
        $glitchInterval = random_int(3, 7);
        $effect = $effects[array_rand($effects)];

        $content = '';

        if (self::$_lastGlitch == 0 || (time() - self::$_lastGlitch) > $glitchInterval) {
            $effect = $effects[array_rand($effects)];
            $this->glitch($this->_splashData, [$effect => true]);
            self::$_lastGlitch = time();
        } else {
            $splash = explode(PHP_EOL, $this->_splashData);
            foreach ($splash as $line => $data) {
                $splash[$line] = "\033[37m{$data}\033[0m";
            }
            $content = implode(PHP_EOL, $splash);
        }

        $content .= PHP_EOL . "            ";
        $content .= "Workspace Intelligence Shell Environment\033[90m\033[0m";
        $content .= PHP_EOL . PHP_EOL;
        $content .= "Running WISE version 0.0.1, produced by \033[36mBlue Fission\033[0m.";
        $content .= PHP_EOL;
        $content .= "\033[32mJen\033[0m interpreter running version 0.0.1." . PHP_EOL . PHP_EOL;

        $this->_content->val($content);
    }
    
}