<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Cli\Console;
use BlueFission\Behavioral\Behaviors\{Event, Action, Meta};
use BlueFission\Str;
use BlueFission\Wise\Cmd\CommandSuggester;

class REPL extends Component
{
    use Traits\CanResize;
    
    protected TextOutput $_textOutput;
    protected Prompt $_prompt;
    protected Cursor $_cursor;
    protected Text $_hint;
    protected CommandSuggester $_suggester;
    protected string $_hintContent = '';

    public function __construct(string|Str|Component $content = '', int $bufferSize = 1024)
    {
        parent::__construct(0, 0, 80, 24, '', 0);
        $this->_textOutput = new TextOutput(0, 0, $this->getWidth(), $this->getHeight() - 1, $bufferSize);
        $this->_prompt = new Prompt(0, $this->getHeight() - 1, $this->getWidth(), '', 1, true);
        $this->_hint = new Text(0, $this->getHeight() - 1, $this->getWidth(), 1, '', 2, false, false);
        $this->_cursor = new Cursor($this->_prompt->getLength(), $this->getHeight() - 1, 3);
        $this->_suggester = new CommandSuggester();

        if ($content != '') {
            $this->addContent($content);
        }

        $this->addChild($this->_textOutput);
        $this->addChild($this->_prompt);
        $this->addChild($this->_hint);
        $this->addChild($this->_cursor);
    }

    public function addContent(string|Str|Component $content) {
        if ($content instanceof Component) {
            $this->_textOutput->addChild($content);
            return;
        }

        if ($content instanceof Str) {
            $content = $content->val();
        }
        
        $this->_textOutput->addLine($content);
    }

    public function setContent(string $content): void
    {
        $this->addContent($content);
    }

    public function setConsole( Console $console ): IDrawable
    {
        $this->_textOutput->setConsole($console);
        $this->_prompt->setConsole($console);
        $this->_cursor->setConsole($console);

        $console->when(Action::PROCESS, function($b, $m) {
            $data = $m->data[0];
            if ( !$data?->content || !$data?->channel ) return;

            if ($data?->channel == 'stdio') {
                $this->handleInput($data?->content);
            }
        });
        $console->when(Event::RECEIVED, function($b, $m) {
            $data = $m->data[0];
            if ( !$data?->content || !$data?->channel ) return;

            if ($data?->channel == 'stdio') {
                $this->readInput($data?->content);
            }

            if ($data?->channel == 'system') {
                if ( $this->_console?->getDisplayMode() == Console::DYNAMIC_MODE ) {
                    // $this->addContent(PHP_EOL);
                }
                $this->addContent("\033[33m".$data?->content."\033[39m" ?? null);
                echo "\n"; // TODO, handle this through the driver
            }
        });

        return parent::setConsole($console);
    }

    public function updatePromptContent(string $content): void
    {
        $this->_prompt->updateContent($content);
    }

    public function update(): void
    {
        $newWidth = $this->getWidth();
        $newHeight = $this->getHeight();

        if ( $this->_parent ) {
            $newWidth = $this->_parent->getWidth();
            $newHeight = $this->_parent->getHeight();
        }

        $this->setDimensions($newWidth, $newHeight);
        $this->_textOutput->setDimensions($newWidth, max(1, $newHeight - 1));
        $this->_prompt->setDimensions($newWidth, 1);
        $this->_prompt->setY($newHeight - 1);
        $this->updateHintPosition();
        $this->_cursor->setPosition($this->_prompt->getLength(), $this->_prompt->getY());
        parent::update();
    }

    public function readInput($input = null): void
    {
        if ($input) {
            $this->updatePromptContent($input);
            $this->updateHint($input);
            $this->_cursor->setPosition($this->_prompt->getLength(), $this->_prompt->getY());
        }
    }

    public function handleInput($input = null): void
    {
        if ($input) {
            $this->_prompt->setActive(false);

            if ($this->_console && $this->_console->getDisplayMode() === Console::DYNAMIC_MODE) {
                $lines = $this->_prompt->draw();
                foreach ($lines as $line) {
                    $this->_textOutput->addLine($line);
                }
            }

            if ($this->_console) {
                $this->_console->perform(Event::PROCESSED, new Meta(data: $input));
            }

            $this->newPrompt();
        }
    }

    public function newPrompt(): void
    {
        $this->_prompt->updateContent('');
        $this->_prompt->setActive(true);
        $this->updateHint('');
        $this->_cursor->setPosition($this->_prompt->getLength(), $this->_prompt->getY());
    }

    private function updateHintPosition(): void
    {
        $hintX = $this->_prompt->getLength();
        $hintY = $this->_prompt->getY();
        $availableWidth = max(0, $this->getWidth() - $hintX);

        $this->_hint->setPosition($hintX, $hintY);
        $this->_hint->setDimensions($availableWidth, 1);
    }

    private function updateHint(string $input): void
    {
        $hint = $this->_suggester->hint($input);
        if ($hint === $this->_hintContent) {
            return;
        }

        $this->_hintContent = $hint;
        $this->_hint->setContent($hint === '' ? '' : "\033[90m{$hint}\033[0m");
        $this->updateHintPosition();
    }
}
