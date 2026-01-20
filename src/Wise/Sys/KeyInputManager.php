<?php

namespace BlueFission\Wise\Sys;

use BlueFission\Wise\Sys\Utl\KeyInputUtil;
use BlueFission\Wise\Sys\IO\IInputSource;

class KeyInputManager {

    private ?IInputSource $_inputSource = null;
    private bool $_exitOnEnd = false;
    private bool $_ended = false;

    public function __construct(?IInputSource $inputSource = null, ?bool $exitOnEnd = null)
    {
        if ($inputSource) {
            $this->setInputSource($inputSource, $exitOnEnd);
        } elseif ($exitOnEnd !== null) {
            $this->_exitOnEnd = (bool)$exitOnEnd;
        }
    }

	public function initialize() {
		// Initialize the key input manager
	}

	public function capture() {
        if ($this->_inputSource) {
            $input = $this->_inputSource->read();
            if ($input === null) {
                if ($this->_exitOnEnd && !$this->_ended) {
                    $this->_ended = true;
                    return "exit" . PHP_EOL;
                }
                $this->_ended = true;
                return '';
            }

            return $input;
        }

		// Listen for key input
		return KeyInputUtil::listen();
	}

    public function setInputSource(?IInputSource $inputSource, ?bool $exitOnEnd = null): void
    {
        $this->_inputSource = $inputSource;
        $this->_ended = false;

        if ($exitOnEnd !== null) {
            $this->_exitOnEnd = (bool)$exitOnEnd;
        }
    }
}
