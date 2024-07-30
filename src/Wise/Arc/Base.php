<?php

namespace BlueFission\Wise\Arc;

use BlueFission\Wise\Sys\Utl;

class Base {

	protected $_kernel;

	protected $_splashData = '';

	protected static $_lastGlitch = 0;

	public function __construct(Kernel $kernel) {
		$this->_kernel = $kernel;

		$this->splashData();
	}

	protected function splashData() {
		$this->_splashData = "
            ██╗    ██╗██╗███████╗███████╗
            ██║    ██║██║██╔════╝██╔════╝
            ██║ █╗ ██║██║███████╗█████╗  
            ██║███╗██║██║╚════██║██╔══╝  
            ╚███╔███╔╝██║███████║███████╗
             ╚══╝╚══╝ ╚═╝╚══════╝╚══════╝";
	}

	public function splash() {
		$effects = ['randomcolor', 'jumble', 'jitter', 'corrupt'];

		$glitch_interal = random_int(3, 7);
		$effect = $effects[array_rand($effects)];

		if ( self::$_lastGlitch == 0 || (time() - self::$_lastGlitch) > $glitch_interal ) {
			$effect = $effects[array_rand($effects)];
			$this->glitch($this->_splashData, [$effect=>true]);
			self::$_lastGlitch = time();
		} else {
			$splash = explode(PHP_EOL, $this->_splashData);
			foreach ($splash as $line=>$data) {
				// assign ANSI white to each line
				$splash[$line] = "\033[37m{$data}\033[0m";
			}
			$this->display(implode(PHP_EOL, $splash));			
		}

        $this->display(PHP_EOL . "            ");

        $this->display("Workspace Intelligence Shell Environment", ['color'=>'gray']);

        $this->display(PHP_EOL . PHP_EOL);

        $this->display("Running WISE version 0.0.1, produced by ");
        $this->display("Blue Fission", ['color'=>'cyan']);
        $this->display("." . PHP_EOL);

        $this->display("Jen", ['color'=>'green']);
        $this->display(" interpreter running version 0.0.1." . PHP_EOL . PHP_EOL);

        // $this->assistant();
	}

	public function assistant() {
		$this->type("Loading C.O.E.U.S. - Command Oriented Efficiency and Utility System" . PHP_EOL);
	}

	protected function display($data, $args = null) {
		$this->_kernel->display($data, $args);
	}

	protected function type($data) {
		$this->_kernel->runAsync((function() use ($data) {
			foreach (str_split($data) as $char) {
				$this->display($char);
				// usleep(100000);
			}
			echo "test";
		})->bindTo($this, $this));
	}

	protected function glitch($data, $args = null) {
		$lines = explode(PHP_EOL, $data);
		$colors = ['red'=>31, 'green'=>32, 'yellow'=>33, 'blue'=>34, 'magenta'=>35, 'cyan'=>36, 'white'=>36];
		$ascii = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '+', '=', '[', ']', '{', '}', '|', '\\', '/', '<', '>', '?', ',', '.', ':', ';', '"', "'"];
			

		if ( $args && isset($args['randomcolor']) )
		{
			foreach ( $lines as $offset=>$line ) {
				$lines[$offset] = "\033[{$colors[array_rand($colors)]}m{$line}\033[0m";
			}
		}

		if ( $args && isset($args['corrupt']) )
		{
			// Preserve the orginal text, except change up to 3 random characters per line 
			// with a highlighted character with a random color and random ascii symbol
			foreach ($lines as $offset=>$line) {
				$chars = str_split($line);
				$charCount = count($chars);
				$charCount = $charCount > 3 ? 3 : $charCount;
				if (!is_array($chars) || count($chars) <= 0 ) continue;
				$randChars = array_rand($chars, $charCount);
				if ($randChars == 0) continue;
				foreach ($randChars as $randChar) {
					// $chars[$randChar] = $ascii[array_rand($ascii)], $colors[array_rand($colors)];
					$color = $colors[array_rand($colors)];
					$data = $ascii[array_rand($ascii)];

					$chars[$randChar] = "\033[{$color}m{$data}\033[0m";
				}
				$lines[$offset] = implode('', $chars);
			}
		}

		if ( $args && isset($args['jumble']) )
		{
			// Preserve the orginal text, except jumble the characters in each line
			foreach ($lines as $offset=>$line) {
				$chars = str_split($line);
				shuffle($chars);
				$lines[$offset] = implode('', $chars);
			}
		}

		if ( $args && isset($args['jitter']) ) 
		{
			foreach ($lines as $offset=>$line) {
				$inOrOut = rand(0,1);
				if ( $inOrOut == 1 ) {
					$line = " " . $line;
				} elseif ( strpos($line, ' ') !== false ) {
					$line = substr($line, 1);
				}

				$lines[$offset] = $line;
			}
		}

		if ( $args && isset($args['random']) )
		{
			for ($i = 0; $i < count($lines); $i++) {
				$line = $lines[$i];
				$glitch = '';
				for ($j = 0; $j < strlen($line); $j++) {
					$glitch .= chr(rand(33, 126));
				}
				$lines[$i] = $glitch;
			}
		}

		$this->display(implode(PHP_EOL, $lines));
	}
}