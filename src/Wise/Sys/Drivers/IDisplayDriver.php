<?php

namespace BlueFission\Wise\Sys\Drivers;

interface IDisplayDriver {
	public function handle( $data, $type = null, $style = null );
	public function send( $data );
}