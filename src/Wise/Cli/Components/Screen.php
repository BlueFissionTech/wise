<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;

class Screen extends Component
{
    use Traits\CanResize;

    public function __construct()
    {
        // @TODO: This is a hack, we should get this from the Console
        list($width, $height) = ConsoleDisplayUtil::getTerminalSize();
        parent::__construct(0, 0, $width, $height, '', 0);
    }

    public function update(): void
    {
        list($width, $height) = ConsoleDisplayUtil::getTerminalSize();
        $this->_width->val($width);
        $this->_height->val($height);

        parent::update();
    }
}