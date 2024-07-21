<?php

namespace BlueFission\Wise\Sys\Drivers;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;

class ConsoleDisplayDriver implements IDisplayDriver {
	public function handle( $data, $type = null, $style = null ) {
		
		$style = $this->formatStyle($style);
		
		$this->displayFormatted($data, $type, $style);
	}

	protected function formatStyle( $style = null ) {
		switch ( $style ) {
			case 'black':
			$style = ConsoleDisplayUtil::COLOR_BLACK;
			break;
			case 'red':
			$style = ConsoleDisplayUtil::COLOR_RED;
			break;
			case 'green':
			$style = ConsoleDisplayUtil::COLOR_GREEN;
			break;
			case 'yellow':
			$style = ConsoleDisplayUtil::COLOR_YELLOW;
			break;
			case 'blue':
			$style = ConsoleDisplayUtil::COLOR_BLUE;
			break;
			case 'magenta':
			$style = ConsoleDisplayUtil::COLOR_MAGENTA;
			break;
			case 'cyan':
			$style = ConsoleDisplayUtil::COLOR_CYAN;
			break;
			case 'white':
			$style = ConsoleDisplayUtil::COLOR_WHITE;
			break;
			case 'gray':
			$style = ConsoleDisplayUtil::COLOR_GRAY;
			break;
			case 'default':
			default:
			$style = ConsoleDisplayUtil::COLOR_DEFAULT;
			break;
		}

		return $style;
	}

	protected function displayFormatted($data, $type = null, $style = null) {
		switch ( $type ) {
			case 'color':
				ConsoleDisplayUtil::colorize( $data, $style );
				break;
			case 'error':
				ConsoleDisplayUtil::colorize( $data, 'red' );
				break;
			case 'warning':
				ConsoleDisplayUtil::colorize( $data, 'yellow' );
				break;
			case 'success':
				ConsoleDisplayUtil::colorize( $data, 'green' );
				break;
			case 'info':
				ConsoleDisplayUtil::colorize( $data, 'blue' );
				break;
			case 'highlight':
				ConsoleDisplayUtil::highlight( $data );
				break;
			case 'underline':
				ConsoleDisplayUtil::underline( $data );
				break;
			case 'bold':
				ConsoleDisplayUtil::bold( $data );
				break;
			case 'italic':
				ConsoleDisplayUtil::italic( $data );
				break;
			default:
				ConsoleDisplayUtil::display( $data );
				break;
		}
	}

	public function init() {

	}

	public function update() {
		ConsoleDisplayUtil::update();
	}

	public function draw() {
		ConsoleDisplayUtil::draw();
	}

	public function clear() {
		ConsoleDisplayUtil::clear();
	}

	public function clearScreen() {
		ConsoleDisplayUtil::clearScreen();
	}
}