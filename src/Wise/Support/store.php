<?php

use BlueFission\Utils\Util;

if (!function_exists('store')) {
    function store(string $name, $value = null)
    {
        return Util::store($name, $value);
    }
}
