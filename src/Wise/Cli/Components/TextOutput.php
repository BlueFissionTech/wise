<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;
use BlueFission\Arr;

class TextOutput extends Component
{
    use Traits\CanResize;
    use Traits\Collides;
    use Traits\CollidesChildren;

    protected int $_bufferSize;
    protected Arr $_lines;
    protected int $_scrollTop; // The topmost visible line of the content

    public function __construct(int $x = 0, int $y = 0, int $width = 80, int $height = 24, int $bufferSize = 1024, int $zIndex = 0)
    {
        parent::__construct($x, $y, $width, $height, '', $zIndex);
        $this->_bufferSize = $bufferSize;
        $this->_lines = Arr::make();

        $this->_scrollTop = 0;
    }

    public function addLine(string $line): void
    {
        $this->addChild(new Text(0, 0, $this->getWidth(), 1, $line, 0, true, false));

        $this->_scrollTop = $this->calculateScrollTop();

        $this->update();
    }

    protected function calculateScrollTop(): int
    {
        if ($this->_lines->size() <= $this->getHeight()) {
            return 0;
        }

        $scrollTop = $this->_lines->size() - $this->getHeight();

        return $scrollTop;
    }

    public function update(): void
    {
        $newBottom = 0;
        foreach ($this->_children as $child) {
            if (method_exists($child, 'setY')) {
                $child->setY($newBottom);
            }
            $newBottom += $child->getHeight();
        }

        $newWidth = $this->getWidth();
        $newHeight = $this->getHeight();

        if ( $this->_parent ) {
            $newWidth = $this->_parent->getWidth();
            $newHeight = $this->_parent->getHeight();
        }

        $this->setDimensions($newWidth, $newHeight);

        foreach ($this->_children as $key => $child) {
            if ($child->getY() + $child->getHeight() < 0) {
                unset($this->_children[$key]);
            }
        }
    }

    public function draw(): array
    {
        $contents = parent::draw();

        return $contents;

        $this->_lines = Arr::make(explode(PHP_EOL, $contents));

        // Show only the visible portion of contents given the current scrollTop
        $visibleContent = $this->_lines->slice($this->_scrollTop, $this->getHeight());
        $this->_content = implode(PHP_EOL, $visibleContent);

        return $visibleContent;
    }

    public function getCharacterAtPosition(int $x, int $y): string
    {
        $line = ConsoleDisplayUtil::parseAnsiCodes($this->_lines[$y] ?? '')['content'] ?? '';
        return $line[$x] ?? ' ';
    }

    public function scroll(int $top): void
    {
        $this->_scrollTop = $top;
    }

    public function clear(): void
    {
        $this->_lines->val([]);
        $this->update();
    }
}