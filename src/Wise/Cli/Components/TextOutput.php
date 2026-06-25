<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;
use BlueFission\Wise\Cli\Console;
use BlueFission\Arr;

class TextOutput extends Component
{
    use Traits\CanResize;
    use Traits\Collides;
    use Traits\CollidesChildren;

    protected int $_bufferSize;
    protected Arr $_lines;
    protected int $_scrollTop; // The topmost visible line of the content
    protected int $_sequence = 0;

    public function __construct(int $x = 0, int $y = 0, int $width = 80, int $height = 24, int $bufferSize = 1024, int $zIndex = 0)
    {
        parent::__construct($x, $y, $width, $height, '', $zIndex);
        $this->_bufferSize = $bufferSize;
        $this->_lines = Arr::make();

        $this->_scrollTop = 0;
    }

    public function addLine(string $line): void
    {
        $this->_sequence++;
        $this->addChild(new Text(0, 0, $this->getWidth(), 1, $line, $this->_sequence, true, false));

        if ( $this->_console?->getDisplayMode() == Console::DYNAMIC_MODE ) {
            $this->_scrollTop = $this->calculateScrollTop();
            $this->update();
        }
    }

    public function addChild(IDrawable $child): void
    {
        parent::addChild($child);
        if ( $this->_console?->getDisplayMode() == Console::STATIC_MODE ) {
            $child->update();
            $output = $child->draw();
            $this->_console->send(implode(PHP_EOL, $output));
        }
    }

    public function removeChild(IDrawable $child): void
    {
        parent::removeChild($child);
        $this->_needsRedraw = true;
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
        $this->_lines = Arr::make($contents);

        return $contents;
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
