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
        if (getenv('WISE_SKIP_PY_DEPS')) {
            return;
        }

        $python = getenv('WISE_PYTHON') ?: 'python';
        $requirementsPath = 'scripts/requirements.txt';
        $requirements = @file($requirementsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($requirements === false) {
            throw new \Exception("Failed to read Python requirements at {$requirementsPath}");
        }

        $pyVersion = self::getPythonVersion($python);
        $skipPlaysound = false;
        if ($pyVersion && PHP_OS_FAMILY === 'Windows') {
            $skipPlaysound = ($pyVersion['major'] > 3 || ($pyVersion['major'] === 3 && $pyVersion['minor'] >= 12));
        }

        if ($skipPlaysound) {
            $requirements = array_values(array_filter($requirements, fn($line) => trim($line) !== 'playsound'));
        }

        $installPath = $requirementsPath;
        $tempPath = null;
        if ($skipPlaysound) {
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-requirements.txt';
            file_put_contents($tempPath, implode(PHP_EOL, $requirements) . PHP_EOL);
            $installPath = $tempPath;
            echo "Skipping playsound on Windows/Python {$pyVersion['major']}.{$pyVersion['minor']}.\n";
        }

        $output = [];
        $return_var = 0;
        $cmd = escapeshellarg($python) . ' -m pip install -r ' . escapeshellarg($installPath);
        exec($cmd, $output, $return_var);

        if ($tempPath && file_exists($tempPath)) {
            unlink($tempPath);
        }

        if ($return_var !== 0) {
            throw new \Exception("Failed to install Python dependencies: " . implode("\n", $output));
        }
    }

    private static function getPythonVersion(string $python): ?array
    {
        $output = [];
        $return_var = 0;
        $cmd = escapeshellarg($python) . ' -c "import sys; print(sys.version_info[0]); print(sys.version_info[1])"';
        exec($cmd, $output, $return_var);
        if ($return_var !== 0 || count($output) < 2) {
            return null;
        }

        return [
            'major' => (int)$output[0],
            'minor' => (int)$output[1],
        ];
    }
}
