<?php

namespace BlueFission\Wise\Sys\Drivers;

use BlueFission\Wise\Sys\Utl\ConsoleDisplayUtil;

class ConsoleDisplayDriver implements IDisplayDriver {
	public function handle( $data ) {
		ConsoleDisplayUtil::display( $data );
	}
}