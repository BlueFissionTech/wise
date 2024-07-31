<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait CanResize {

    public function setWidth(int $width): void
    {
        if ( $this->_width->val() != $width) {
            $this->_needsRedraw = true;
        }

        $this->_width->val($width);
    }

    public function setHeight(int $height): void
    {
        if ( $this->_height->val() != $height) {
            $this->_needsRedraw = true;
        }

        $this->_height->val($height);
    }

    public function setDimensions(int $width, int $height): void
    {
        $this->setWidth($width);
        $this->setHeight($height);
    }

}