<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;

class Text extends Component
{
    use Traits\CanResize;
    use Traits\CanMove;

    protected bool $_responsive;
    protected bool $_overflow;

    public function __construct(int $x = 0, int $y = 0, int $width = 10, int $height = 5, string $content = '', int $zIndex = 0, bool $overflow = false, bool $responsive = false)
    {
        parent::__construct($x, $y, $width, $height, $content, 0);
        $this->_responsive = $responsive;
        $this->_overflow = $overflow;

        if ( $this->_overflow ) {
            $this->calculateOverflow();
        }
    }
    
    public function update(): void
    {
        if ( $this->_responsive ) {
            $newWidth = $this->getWidth();
            $newHeight = $this->getHeight();

            if ( $this->_parent ) {
                $newWidth = $this->_parent->getWidth();
                $newHeight = $this->_parent->getHeight();
            }

            $this->setDimensions($newWidth, $newHeight);
        }

        if ( $this->_overflow ) {
            $this->calculateOverflow();
        }

        parent::update();
    }

    protected function calculateOverflow(): void
    {
        $width = $this->getWidth();
        $lines = explode(PHP_EOL, wordwrap($this->_content, $width, PHP_EOL, $width > 0 ? true : false));
        $numLines = count($lines);

        if ( $numLines > $this->getHeight() ) {
            $this->setHeight( $numLines );
        }
    }
}