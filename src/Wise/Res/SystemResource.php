<?php
// SystemResourc.php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\System\Machine;
use BlueFission\Behavioral\Behaviors\Behavior;

class SystemResource extends Service
{
    private $machine;

    public function __construct()
    {
        $this->machine = new Machine();
        parent::__construct();
    }

    public function handle(Behavior $behavior, $args)
    {
        $flag = '';
        if (isset($args) && count($args) >= 1) {
            $flag = $args[0];
        }

        if ($flag == 'resources' || $flag == 'status') {
            $this->resources();
        } elseif ($flag == 'help') {
            $this->_response = $this->help();
        } else {
            $this->_response = "I'm not sure how to handle that request. Type 'get system help' for available options.";
        }
    }

    private function resources()
    {
        try {
            $memoryUsage = $this->machine->getMemoryUsage();
            $memoryPeakUsage = $this->machine->getMemoryPeakUsage();
            $uptime = $this->machine->getUptime();
            $cpuUsage = $this->machine->getCPUUsage();
            $temperature = $this->machine->getTemperature();
            $fanSpeed = $this->machine->getFanSpeed();
            $powerConsumption = $this->machine->getPowerConsumption();
        } catch (\Exception $e) {
            $this->_response = "Error: There was an issue retrieving system resources.";
            return;
        }

        $this->_response = "System Resources:\n" .
            "Memory Usage: {$memoryUsage}\n" .
            "Memory Peak Usage: {$memoryPeakUsage}\n" .
            "Uptime: {$uptime}\n" .
            "CPU Usage: {$cpuUsage}\n" .
            "Temperature: {$temperature}\n" .
            "Fan Speed: {$fanSpeed}\n" .
            "Power Consumption: {$powerConsumption}\n";
    }

    private function help(): string
    {
        return "Available commands for the System Resource Manager:\n" .
            "- get system resources: Retrieve information about system resources (memory usage, CPU usage, etc.).\n" .
            "- get system help: Show this help message.";
    }
}
