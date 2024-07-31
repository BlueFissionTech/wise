<?php

namespace BlueFission\Wise\Cli;

use BlueFission\Wise\Sys\{
    DisplayManager,
    KeyInputManager,
};
use BlueFission\Wise\Cli\Components\{IDrawable, Component, Cursor, Prompt};
use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;
use BlueFission\Behavioral\{Behaves, IDispatcher, IBehavioral};
use BlueFission\Behavioral\Behaviors\{Event, Action, State, Meta};
use BlueFission\Collections\Collection;
use BlueFission\{Str, Arr};

class Console implements IDispatcher, IBehavioral
{
    use Behaves {
        Behaves::__construct as private __bConstruct;
    }

    const STATIC_MODE = 'static';
    const DYNAMIC_MODE = 'dynamic';

    protected $_displayMode;

    protected $_displayManager;
    protected $_keyInputManager;

    protected string $_buffer = '';
    protected Collection $_components;
    protected array $_content = [];
    protected array $_size = [];
    protected Arr $_inputChannels;
    protected Arr $_specialChars;

    protected ?Cursor $_activeCursor = null;
    protected ?Prompt $_activePrompt = null;

    public function __construct(DisplayManager $displayManager, KeyInputManager $keyInputManager)
    {
        $this->_displayManager = $displayManager;
        $this->_keyInputManager = $keyInputManager;
        $this->_components = new Collection();
        $this->_inputChannels = Arr::make();

        $this->_displayMode = self::STATIC_MODE;

        $this->__bConstruct();
    }

    public function setDisplayMode($mode): void
    {
        $this->_displayMode = $mode;
    }

    public function getDisplayMode(): string
    {
        return $this->_displayMode;
    }

    public function addComponent(IDrawable $component): Console
    {
        $this->_components[] = $component;
        // usort($this->_components, fn($a, $b) => $a->getZIndex() <=> $b->getZIndex());
        $this->_components->sort(fn($a, $b) => $a->getZIndex() <=> $b->getZIndex());
        $this->checkForActiveComponents($component);
        $component->setConsole($this);
        
        return $this;
    }

    public function removeComponent(IDrawable $component): Console
    {
        // $this->_components = array_filter($this->_components, fn($c) => $c !== $component);
        $this->_components->filter(fn($c) => $c !== $component);
        if ($component === $this->_activeCursor) {
            $this->_activeCursor = null;
        }
        if ($component === $this->_activePrompt) {
            $this->_activePrompt = null;
        }

        return $this;
    }

    public function registerInteractives(IDrawable $component): Console
    {
        $this->checkForActiveComponents($component);

        return $this;
    }

    public function registerInputChannel($name): Console
    {
        $this->_inputChannels->set($name, '');

        return $this;
    }

    public function output(string $content, ?string $channelName = null): void
    {
        if ( !$channelName ) {
            $channelName = $this->_inputChannels->keys()->shift();
        }

        if (! $this->_inputChannels->keys()->has($channelName) ) {
            throw(new \Exception('Input channel not registered'));
        }

        $this->_inputChannels[$channelName] = $content;
        $this->trigger(Event::RECEIVED, new Meta(data: new Data( channel: $channelName, content: $content)));
    }

    protected function checkForActiveComponents(IDrawable $component): void
    {
        if ($component instanceof Cursor) {
            $this->_activeCursor = $component;
        }
        if ($component instanceof Prompt) {
            $this->_activePrompt = $component;
        }

        foreach ($component->getChildren() as $child) {
            $this->checkForActiveComponents($child);
        }
    }

    public function render(): Console
    {
        // $this->_size = $this->getDisplaySize();

        $lines = [];

        foreach ($this->_components as $component) {
            // @TODO: Disable subsequent component updates to screen in `static` mode (for LLM agents and "chat" based output)
            $component->update();
            $componentLines = $component->draw();
            $x = $component->getX();
            $y = $component->getY();

            foreach ($componentLines as $index => $line) {
                $parsedLine = ConsoleDisplayUtil::parseAnsiCodes($line);
                $lineContent = $parsedLine['content'];
                $lineLength = mb_strlen($lineContent);

                $targetLine = $y + $index;
                if (!isset($lines[$targetLine])) {
                    $lines[$targetLine] = str_repeat(' ', $x) . $line;
                } else {
                    $lines[$targetLine] = substr_replace(
                        $lines[$targetLine],
                        $lineContent,
                        $x,
                        $lineLength
                    );
                }
            }
        }

        $this->addToContent(implode(PHP_EOL, $lines));

        return $this;
    }


    public function addToContent($input, $args = []) {
        $lines = explode(PHP_EOL, $input);
        foreach($lines as $line) {
            $this->_content[] = ['line'=>$line, 'args'=>$args];
        }
    }

    public function send($input) {
        $this->_displayManager->send($input);
    }

    public function update(): Console 
    {
        $this->_displayManager->update();

        return $this;
    }

    public function display(): Console
    {
        if ( $this->needsRedraw() ) {
            $this->update()->render()->draw();
        } else {
            $this->print();
        }

        return $this;
    }

    public function print(): Console
    {
        if ( count($this->_content) == 0 ) {
            return $this;
        }

        foreach($this->_content as $content) {
            $this->_displayManager->display($content['line'] . PHP_EOL, $content['args']);
        }
        $this->_displayManager->print();
        $this->_content = [];

        return $this;
    }

    public function draw(): Console 
    {
        foreach($this->_content as $content) {
            $this->_displayManager->display($content['line'] . PHP_EOL, $content['args']);
        }

        $this->_displayManager->draw();

        // If there is an active cursor drawn to the screen, move the console cursor to that position
        if ($this->_activeCursor) {
            echo "\033[" . $this->_activeCursor->getAbsoluteY() . ";" . $this->_activeCursor->getAbsoluteX() . "H";
        }

        $this->_content = [];

        return $this;
    }

    public function listen(): Console
    {
        // If there is a `Prompt` drawn to the screen whose `Prompt::getActive()` is `true`, then listen for input
        if ($this->_activePrompt && $this->_activePrompt->getActive()) {
            $this->perform(State::RECEIVING);
            $input = $this->input();
            if ($input != '') {
                $this->processInput($input);
            }
            $this->halt(State::RECEIVING);
        }

        // On return key press, set the `Prompt::active` property to `false`
        return $this;
    }

    public function inputSilent( $prompt = "" ) {
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
              $vbscript, 'wscript.echo(InputBox("'
              . addslashes($prompt)
              . '", "", "password here"))');
            $command = "cscript //nologo " . escapeshellarg($vbscript);
            $password = rtrim(shell_exec($command));
            unlink($vbscript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
                trigger_error("Can't invoke bash");
                return;
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
              . addslashes($prompt)
              . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    public function input( $input = null ) {
        if ( $input || $input = $this->capture() ) {
            return $input;
        }
        return '';
    }

    protected function capture() {
        return $this->_keyInputManager->capture();
    }

    public function needsRedraw() {
        if ( $this->getDisplayMode() == self::STATIC_MODE && count($this->_content) > 0) { 
            return false;
        }

        foreach( $this->_components as $component ) {
            if ( $component->needsRedraw() ) {
                return true;
            }
        }

        return false;
    }

    private function processInput($input) {

        if ( !isset($this->_specialChars) ) {
            // Special characters like backspace, up, down, etc
            $this->_specialChars = Arr::make([
                "\e[A" => 'UP',
                "\e[B" => 'DOWN',
                "\e[C" => 'RIGHT',
                "\e[D" => 'LEFT',
                "\e[3~" => 'DELETE',
                "\e[1~" => 'HOME',
                "\e[4~" => 'END',
                "\e[5~" => 'PAGE_UP',
                "\e[6~" => 'PAGE_DOWN',
                "\e[2~" => 'INSERT',
                "\e" => 'ESCAPE',
                "\x7F" => 'BACKSPACE',
                "\x0D" => 'NEWLINE',
                "\n" => 'ENTER',
                "\r" => 'RETURN',
                "\t" => 'TAB',
                "\0" => 'NULL',
                "\x1B" => 'ESC',
                // Arrow keys
                "\033[A" => 'UP',
                "\033[B" => 'DOWN',
                "\033[C" => 'RIGHT',
                "\033[D" => 'LEFT',
            ]);
        }

        if (! $this->_buffer) {
            $this->_buffer = '';
        }

        if ( Str::pos($input, $this->_specialChars->flip()->get('BACKSPACE')) === 0 ) {
            // Handle backspace
            $this->_buffer = Str::use()->sub(0, -1);
        } elseif ( Str::pos($input, $this->_specialChars->flip()->get('ENTER')) === 0 ) {
            // Handle enter
            $this->trigger(Action::PROCESS, new Meta(data: new Data( channel: 'stdio', content: $this->_buffer)));
            $this->addToContent("\n");
            $this->_buffer = '';
        } elseif ( Str::pos($input, $this->_specialChars->flip()->get('UP')) === 0 ) {
            // Handle up arrow
        } elseif ( Str::pos($input, $this->_specialChars->flip()->get('DOWN')) === 0 ) {
            // Handle down arrow
        } elseif ( Str::pos($input, $this->_specialChars->flip()->get('RIGHT')) === 0 ) {
            // Handle right arrow by moving the cursor
            $this->send("\033[1C");
        } elseif ( Str::pos($input, $this->_specialChars->flip()->get('LEFT')) === 0 ) {
            // Handle left arrow by moving the cursor
            $this->send("\033[1D");
        } else {
            $this->_buffer .= $input;
        }

        $this->trigger(Event::RECEIVED, new Meta(data: new Data( channel: 'stdio', content: $this->_buffer)));
    }

    public function clear() {
        $this->_content = [];
        $this->_displayManager->clear();
    }

    public function clearScreen() {
        $this->_displayManager->clearScreen();
    }

    public function getDisplaySize() {
        return $this->_displayManager->getSize();
    }
}