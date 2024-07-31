<?php

namespace BlueFission\Wise\Cli\Components;

class Prompt extends Component
{
    use Traits\CanMove;
    use Traits\CanResize;

    protected bool $_active;
    protected string $_context;
    protected $_listener;

    public function __construct(int $x = 0, int $y = 0, int $width = 50, string $content = '', int $zIndex = 0, bool $active = true)
    {
        parent::__construct($x, $y, $width, 1, $content, $zIndex);
        $this->_active = $active;
        $this->_context = getcwd();
    }

    public function setListener(callable $listener): void
    {
        $this->_listener = $listener;
    }

    public function updateContent(string $content): void
    {
        $this->_content->val($content);
    }

    public function update(): void
    {
        $this->calculateOverflow();

        parent::update();
    }

    protected function calculateOverflow(): void
    {
        $width = $this->getWidth();
        $lines = explode(PHP_EOL, wordwrap($this->_content->val(), $width, PHP_EOL, $width > 0 ? true : false));
        $numLines = count($lines);

        if ( $numLines > $this->getHeight() ) {
            $this->setHeight( $numLines );
        }
    }

    public function draw(): array
    {
        $prompt = $this->render();

        $lines = explode("\n", wordwrap($prompt, $this->_parent ? $this->_parent->getWidth() : $this->getLength() , "\n", true));

        $this->_needsRedraw = false;

        return $lines;
    }

    public function getWidth(): int
    {
        return $this->_width->val() ? $this->_width->val() : $this->getPrefixLength();
    }

    public function setActive(bool $active): void
    {
        $this->_active = $active;
    }

    public function getActive(): bool
    {
        return $this->_active;
    }

    public function setContext(string $path): bool
    {
        $this->_context = $path;
    }

    public function getLength(): int
    {
        // Strip ANSI codes for display length
        return mb_strlen(preg_replace('/\e[[][A-Za-z0-9];?[0-9]*m?/', '', $this->render()));
    }

    public function getPrefixLength(): int
    {
        return mb_strlen($this->getPrefix());
    }

    protected function getPrefix()
    {
        // Split the path by the Directory Seperator, then recombine to show no more than the last three directories
        $dir = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $this->_context), -3));
        $context = "\033[90m{$dir}\033[0m";

        // If active, render prompt as white, otherwise render as gray
        $prefix = $this->_active ? "\033[97m# \033[0m{$context} \033[97m>\033[0m " : "\033[90m# \033[0m{$context} \033[90m>\033[0m ";

        return $prefix;
    }

    protected function render(): string
    {
        $prefix = $this->getPrefix();

        // Put the full prompt together
        $prompt = $prefix . $this->_content->val();

        return $prompt;
    }
}
