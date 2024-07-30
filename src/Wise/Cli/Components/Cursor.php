<?php

namespace BlueFission\Wise\Cli\Components;

class Cursor extends Component
{
    use Traits\CanMove;

    private string $_color;

    public function __construct(int $x = 0, int $y = 0, int $zIndex = 0)
    {
        // Set ANSI color
        $this->setColor('white');
        parent::__construct($x, $y, 1, 1, '%', $zIndex);
    }

    public function draw(): array
    {
        // $content should update with the character at the current cursor position (space for blank)
        $this->_content = ' ';
        if ($this->_parent && $this->_parent instanceof TextOutput) {
            $this->_content = $this->_parent->getCharacterAtPosition($this->getX(), $this->getY());
        }

        $color = $this->_color;

        // Blinking highlighted block for cursor, blinking every second
        $highlight = (int)(microtime(true) * 2) % 2 === 0;

        $this->_needsRedraw = false;
        
        // remove highlight after content
        // return [ ($highlight ? "\033[7m{$this->_content}\033[0m" : "{$this->_content}") ];
        return [ ($highlight ? "_" : "{$this->_content}") ];

    }

    public function setColor(string $color): void
    {
        // set it to a base ANSI color code that can be used for both character color and highlight color (depending on blink state)
        switch($color) {
            case 'black':
                $this->_color = "\033[30m";
                break;
            case 'red':
                $this->_color = "\033[31m";
                break;
            case 'green':
                $this->_color = "\033[32m";
                break;
            case 'yellow':
                $this->_color = "\033[33m";
                break;
            case 'blue':
                $this->_color = "\033[34m";
                break;
            case 'magenta':
                $this->_color = "\033[35m";
                break;
            case 'cyan':
                $this->_color = "\033[36m";
                break;
            case 'white':
                $this->_color = "\033[37m";
                break;
            default:
                $this->_color = "\033[37m";
        }            

        $this->_color = $color;
    }
}
