<?php

namespace BlueFission\Wise\Sys\Utl;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Connections\Stdio;

class KeyInputUtil {

	private static $_stdio;

	public static function init(Stdio $stdio) {
		self::$_stdio = $stdio;

		self::$_stdio->when(Event::RECEIVED, function($b, $m) {

		});
	}

	public static function listen() {
		return self::$_stdio->query()->result();
	}

	// Allow left and right to move the cursor and up and down to either scroll the screen or go through command history at a prompt
	public static function moveCursor($direction, $distance) {
		die('IM WORKING');
		$dir = ($direction == 'left' || $direction == 'up') ? '-' : '+';
		$distance = abs($distance);
		return self::$_stdio->send("\033[{$distance}{$dir}");
	}

	public static function moveCursorLeft($distance) {
		return self::moveCursor('left', $distance);
	}

	public static function moveCursorRight($distance) {
		return self::moveCursor('right', $distance);
	}

	public static function moveCursorUp($distance) {
		return self::moveCursor('up', $distance);
	}

	public static function moveCursorDown($distance) {
		return self::moveCursor('down', $distance);
	}

	public static function moveCursorToBeginningOfLine() {
		return self::$_stdio->send("\033[0G");
	}

	public static function moveCursorToBeginningOfNextLine() {
		return self::$_stdio->send("\033[1E");
	}

	public static function moveCursorToBeginningOfPreviousLine() {
		return self::$_stdio->send("\033[1F");
	}
}