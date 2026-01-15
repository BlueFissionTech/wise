<?php

namespace BlueFission\Wise\Nav;

interface INavigator
{
    public function process(string $input): string;
}
