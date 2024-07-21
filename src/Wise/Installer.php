<?php

namespace BlueFission\Wise;

class Installer
{
    public static function postInstall()
    {
        self::installPythonDependencies();
    }

    public static function postUpdate()
    {
        self::installPythonDependencies();
    }

    private static function installPythonDependencies()
    {
        $output = [];
        $return_var = 0;
        exec('pip install -r scripts/requirements.txt', $output, $return_var);
        if ($return_var !== 0) {
            throw new \Exception("Failed to install Python dependencies: " . implode("\n", $output));
        }
    }
}
