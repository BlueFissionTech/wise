<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait CanResize {

    public function setWidth(int $width): void
    {
        $this->_width->val($width);
    }

    public function setHeight(int $height): void
    {
        $this->_height->val($height);
    }

    public function setDimensions(int $width, int $height): void
    {
        $this->setWidth($width);
        $this->setHeight($height);
    }

}