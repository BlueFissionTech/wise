<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;
use BlueFission\Wise\Cli\Console;
use BlueFission\Collections\Collection;
use BlueFission\Num;

class Component implements IDrawable
{
    protected Num $_x;
    protected Num $_y;
    protected Num $_width;
    protected Num $_height;
    protected string $_content;
    protected ?IDrawable $_parent;
    protected Collection $_children;
    protected int $_zIndex;
    protected bool $_needsRedraw;
    protected ?Console $_console;

    public function __construct(int $x = 0, int $y = 0, int $width = 10, int $height = 5, string $content = '', int $zIndex = 0)
    {
        $this->_x = Num::make($x);
        $this->_y = Num::make($y);
        $this->_width = Num::make($width);
        $this->_height = Num::make($height);
        $this->_content = $content;
        $this->_parent = null;
        $this->_children = new Collection();
        $this->_zIndex = $zIndex;
        $this->_needsRedraw = true;
        $this->_console = null;

        // Constrain positive-only numerical values
        $this->_x->constraint(fn(&$v) => $v = $v < 0 ? 0 : $v);
        $this->_y->constraint(fn(&$v) => $v = $v < 0 ? 0 : $v);
        $this->_width->constraint(fn(&$v) => $v = abs($v) );
        $this->_height->constraint(fn(&$v) => $v = abs($v) );
    }

    public function getX(): int
    {
        return $this->_x->val();
    }

    public function getY(): int
    {
        return $this->_y->val();
    }

    public function getWidth(): int
    {
        return $this->_width->val();
    }

    public function getHeight(): int
    {
        return $this->_height->val();
    }

    public function getContent(): string
    {
        return $this->_content;
    }

    public function setContent(string $content): void
    {
        $this->_content = $content;
    }

    public function getZIndex(): int
    {
        return $this->_zIndex;
    }

    public function setZIndex(int $zIndex): void
    {
        $this->_zIndex = $zIndex;
    }

    public function draw(): array
    {
        $lines = explode(PHP_EOL, wordwrap($this->_content, $this->getWidth(), PHP_EOL, true));
        $lines = array_slice($lines, 0, $this->getHeight());
        $this->_children->sort(fn($a, $b) => $a->getZIndex() <=> $b->getZIndex());

        $aggregatedAnsiCodes = [];

        foreach ($this->_children as $child) {
            // @TODO: Disable subsequent component updates to screen in `static` mode (for LLM agents and "chat" based output)
            $child->update();
            $childLines = $child->draw();
            $childX = $child->getX();
            $childY = $child->getY();

            foreach ($childLines as $index => $line) {
                $parsedChildLine = ConsoleDisplayUtil::parseAnsiCodes($line);
                $childLineContent = $parsedChildLine['content'];
                $lineLength = mb_strlen($line);
                if (isset($lines[$childY + $index])) {
                    $parsedCurrentLine = ConsoleDisplayUtil::parseAnsiCodes($lines[$childY + $index]);
                    $currentLineContent = $parsedCurrentLine['content'];
                    // This needs to respect the ANSI codes for both the current line and the child line that is overlaying it
                    // based on the `$parsedLine['ansiCodes'][$pos]` return from the `parseAnsiCodes` method
                    $lines[$childY + $index] = substr_replace(
                        $currentLineContent,
                        $childLineContent,
                        $childX,
                        $lineLength
                    );
                } elseif ($childY + $index < $this->getHeight()) {
                    $lines[$childY + $index] = substr(
                        str_pad('', $this->getWidth(), ' '),
                        0,
                        $childX
                    ) . $childLineContent;
                }

                // Replace ANSI codes
                // This will sometime overlap the ANSI codes of one child with another child over iteratiosn,
                // so we need to remap the ANSI codes to the correct position in the line
                // running a diff or something against the previous parse line and any new changes that developed should allow us to
                // map new positions. Now just to do that somehow...
                
                // At some second iteration of child, here, we will have ANSI codes that will cause a mismatch with our cached string length
                // so we will aggregate all ANSI code positions, overwriting them with the newest ones
                foreach ($parsedChildLine['ansiCodes'] as $pos => $ansiCode) {
                    $aggregatedAnsiCodes[$childY + $index][$childX + $pos] = $ansiCode;
                }
            }
        }

        // // Display dimensions on screen
        // $dimensions = '['.$this->getWidth().','.$this->getHeight().']';
        // $lines[0] = substr_replace($lines[0] ?? '', $dimensions, 0, strlen($dimensions));

        foreach ($aggregatedAnsiCodes as $index => $ansiCodes) {
            foreach (array_reverse($ansiCodes, true) as $pos => $ansiCode) {
                $ansiCodeLength = mb_strlen($ansiCode);
                $lines[$index] = $this->mbSubstrReplace(
                    $lines[$index] ?? '',
                    $ansiCode,
                    $pos,
                    0
                );
            }
        }

        $this->_needsRedraw = false;

        return $lines;
    }

    public function needsRedraw(): bool
    {
        foreach ($this->_children as $child) {
            if ( $child->needsRedraw() ) {
                return true;
            }
        }

        return $this->_needsRedraw;
    }

    public function setParent(IDrawable $parent = null): void
    {
        $this->_parent = $parent;
        if ($parent) {
            $parent->registerChildComponent($this);
        }
    }

    public function getParent(): ?IDrawable
    {
        return $this->_parent;
    }

    public function addChild(IDrawable $child): void
    {
        $this->_children[] = $child;
        $child->setParent($this);
        $this->registerChildComponent($child);
    }

    public function removeChild(IDrawable $child): void
    {
        $this->_children->filter(fn($c) => $c !== $child);
        $child->setParent(null);
    }

    public function getChildren(): Collection
    {
        return $this->_children;
    }

    public function getAbsoluteX(): int
    {
        $x = $this->getX();
        $parent = $this->getParent();
        while ($parent) {
            $x += $parent->getX();
            $parent = $parent->getParent();
        }
        return $x;
    }

    public function getAbsoluteY(): int
    {
        $y = $this->getY();
        $parent = $this->getParent();
        while ($parent) {
            $y += $parent->getY();
            $parent = $parent->getParent();
        }
        return $y;
    }

    public function registerChildComponent(IDrawable $component): IDrawable
    {
        if ($component instanceof Cursor || $component instanceof Prompt) {
            if ($this->_parent) {
                $this->_parent->registerChildComponent($component);
            } else if ($this->_console) {
                $this->_console->registerInteractives($component);
            }
        }

        foreach ($component->getChildren() as $child) {
            $component->registerChildComponent($child);
        }

        return $this;
    }

    public function setConsole(Console $console): IDrawable
    {
        $this->_console = $console;
        foreach ($this->_children as $child) {
            $child->setConsole($console);
            $console->registerInteractives($child);
        }
     
        return $this;
    }

    public function update(): void
    {
        // Update logic here
    }

    // via https://stackoverflow.com/questions/11239597/substr-replace-encoding-in-php
    protected function mbSubstrReplace($original, $replacement, $position, $length)
    {
        $startString = mb_substr($original, 0, $position, "UTF-8");
        $endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

        $out = $startString . $replacement . $endString;

        return $out;
    }
}
