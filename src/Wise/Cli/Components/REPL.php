<?php

namespace BlueFission\Wise\Cli\Components;

use BlueFission\Wise\Cli\Console;
use BlueFission\Behavioral\Behaviors\{Event, Action, Meta};
use BlueFission\Str;

class REPL extends Component
{
    use Traits\CanResize;
    
    protected TextOutput $_textOutput;
    protected Prompt $_prompt;
    protected Cursor $_cursor;

    public function __construct(string|Str|Component $content = '', int $bufferSize = 1024)
    {
        parent::__construct(0, 0, 80, 24, '', 0);
        $this->_textOutput = new TextOutput(0, 0, $this->getWidth(), $this->getHeight() - 1, $bufferSize);
        $this->_prompt = new Prompt(0, $this->getHeight() - 1, $this->getWidth(), '', 1, true);
        $this->_cursor = new Cursor($this->_prompt->getLength(), $this->getHeight() - 1, 2);

        if ($content != '') {
            $this->addContent($content);
        }

        $this->_textOutput->addChild($this->_prompt);
        $this->_textOutput->addChild($this->_cursor);

        $this->addChild($this->_textOutput);
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
            if ($data?->channel == 'stdio') {
                $this->handleInput($data?->content ?? null);
            }
        });
        $console->when(Event::RECEIVED, function($b, $m) {
            $data = $m->data[0];
            if ($data?->channel == 'stdio') {
                $this->readInput($data?->content ?? null);
            }

            if ($data?->channel == 'system') {
                $this->addContent($data?->content ?? null);
            }
        });

        return parent::setConsole($console);
    }

    public function updatePromptContent(string $content): void
    {
        $this->_prompt->updateContent($content);
        $this->_cursor->setPosition($this->_prompt->getWidth()+strlen($this->_prompt->getContent()), $this->_prompt->getY());
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
        parent::update();
    }

    public function readInput($input = null): void
    {
        if ($input) {
            $this->updatePromptContent($input);
        }
    }

    public function handleInput($input = null): void
    {
        if ($input) {
            // $this->updatePromptContent($input);
            $this->_prompt->setActive(false);
            $newPrompt = new Prompt(0, $this->getHeight() - 1, $this->getWidth(), '', 1, true);
            $this->_prompt = $newPrompt;
            $this->_textOutput->addChild($newPrompt);
            $this->_cursor->setPosition(0, $newPrompt->getY());

            if ($this->_console) {
                $this->_console->perform(Event::PROCESSED, new Meta(data: $input));
                $this->_console->clear();
            }
        }
    }
}
