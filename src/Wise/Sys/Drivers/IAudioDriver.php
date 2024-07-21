<?php

namespace BlueFission\Wise\Sys\Drivers;

interface IAudioDriver {
	public function handle( $data, $type = null, $style = null );
}