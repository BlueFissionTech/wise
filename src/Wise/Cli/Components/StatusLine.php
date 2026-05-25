<?php

namespace BlueFission\Wise\Cli\Components;

class StatusLine extends Component
{
    use Traits\CanResize;
    use Traits\CanMove;

    protected bool $_responsive;
    protected int $_offsetFromBottom;

    public function __construct(
        int $x = 0,
        int $y = 0,
        int $width = 10,
        int $height = 1,
        string $content = '',
        int $zIndex = 0,
        bool $responsive = true,
        int $offsetFromBottom = 0
    ) {
        parent::__construct($x, $y, $width, $height, $content, $zIndex);
        $this->_responsive = $responsive;
        $this->_offsetFromBottom = max(0, $offsetFromBottom);
    }

    public function setOffsetFromBottom(int $offset): void
    {
        $this->_offsetFromBottom = max(0, $offset);
        $this->_needsRedraw = true;
    }

    public function update(): void
    {
        if ($this->_responsive && $this->_parent) {
            $newWidth = $this->_parent->getWidth();
            $this->setDimensions($newWidth, $this->getHeight());

            if ($this->_offsetFromBottom > 0) {
                $y = max(0, $this->_parent->getHeight() - $this->_offsetFromBottom);
                $this->setY($y);
            }
        }

        parent::update();
    }
}
