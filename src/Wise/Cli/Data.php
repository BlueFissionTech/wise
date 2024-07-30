<?php

namespace BlueFission\Wise\Cli;

class Data {
	public function __construct(public string $channel = '', public ?string $content = null ) {}
}