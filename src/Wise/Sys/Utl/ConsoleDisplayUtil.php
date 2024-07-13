<?php

namespace BlueFission\Wise\Sys\Utl;

use BlueFission\Connections\Stdio;

class ConsoleDisplayUtil {
	const COLOR_BLACK = 30;
	const COLOR_RED = 31;
	const COLOR_GREEN = 32;
	const COLOR_YELLOW = 33;
	const COLOR_BLUE = 34;
	const COLOR_MAGENTA = 35;
	const COLOR_CYAN = 36;
	const COLOR_WHITE = 37;

	const STYLE_BOLD = 1;
	const STYLE_UNDERLINE = 4;
	const STYLE_BLINK = 5;
	const STYLE_REVERSE = 7;
	const STYLE_HIDE = 8;

	protected static $_stdio;

	public static function init(Stdio $stdio) {
		self::$_stdio = $stdio;
	}

	public static function colorize($data, $color) {
		return self::$_stdio->sendColored($data, $color);
	}

	public static function colorizeBackground($data, $color) {
		return self::$_stdio->sendBackground($data, $color);
	}

	public static function highlight($data) {
		return self::$_stdio->sendStyled($data, self::STYLE_BOLD);
	}

	public static function display($data) {
		return self::$_stdio->send($data);
	}

	public static function displayLine($data) {
		return self::$_stdio->send($data . PHP_EOL);
	}

	public static function clear() {
		return self::$_stdio->send("\033[2J\033[1;1H");
	}

	public static function clearLine() {
		return self::$_stdio->send("\033[2K");
	}

	public static function clearLineToBeginning() {
		return self::$_stdio->send("\r");
	}

	public static function clearLineToEnd() {
		return self::$_stdio->send("\033[K");
	}

	public static function blank() {
		return self::$_stdio->send("\033[2J\033[1;1H");
	}

	public static function cursor($x, $y) {
		return self::$_stdio->send("\033[{$y};{$x}H");
	}

	public static function color($color) {
		return self::$_stdio->send("\033[{$color}m");
	}

	public static function reset() {
		return self::$_stdio->send("\033[0m");
	}

	public static function bold() {
		return self::$_stdio->send("\033[1m");
	}

	public static function underline() {
		return self::$_stdio->send("\033[4m");
	}

	public static function blink() {
		return self::$_stdio->send("\033[5m");
	}

	public static function reverse() {
		return self::$_stdio->send("\033[7m");
	}

	public static function hide() {
		return self::$_stdio->send("\033[8m");
	}

	public static function show() {
		return self::$_stdio->send("\033[28m");
	}
}
