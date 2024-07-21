<?php

namespace BlueFission\Wise\Sys\Con;

use BlueFission\Connections\Stdio;
use BlueFission\System\Process;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\IConfigurable;
use BlueFission\Val;
use BlueFission\Arr;
use BlueFission\IObj;

class ExtendedStdio extends Stdio
{
    private $_process;
    private $_pollingScript;

    /**
     * Constructor that sets the configuration data.
     *
     * @param array|null $config Configuration data.
     */
    public function __construct(string $pollingScript = null, $config = null)
    {
        parent::__construct($config);
        $this->initProcess();
        $this->_pollingScript = $pollingScript;
    }

    /**
     * Initializes the child process to handle input.
     */
    private function initProcess()
    {
        if ( $this->_pollingScript ) {
            $this->_pollingScript = 'php';
        }
     
        $this->_process = new Process($this->_pollingScript, null, null, [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "a"],  // stderr
        ]);

        $this->_process->start();

        stream_set_blocking($this->_process->pipes(1), false);
        stream_set_blocking($this->_process->pipes(2), false);

        stream_set_blocking(STDIN, false);
        
        // if linux
        if ( PHP_OS == 'Linux' ) {
            system('stty cbreak');
            system('stty -icanon');
        } elseif ( PHP_OS == 'WINNT' ) {
            // system('mode con:cols=80 lines=25');
        }
    }

    /**
     * Continuously reads data from standard input in a non-blocking way.
     * 
     * @return void
     */
    protected function listen()
    {
        $readStreams = [$this->_connection['in'], $this->_process->pipes(1)];
        $writeStreams = null;
        $exceptStreams = null;
        $timeout = 0; 

        $numChangedStreams = stream_select($readStreams, $writeStreams, $exceptStreams, $timeout);

        $this->_result = "";

        if ($numChangedStreams === false) {
            // Error occurred during stream_select
            $error = "stream_select error";
            error_log('IO Error: ' . $error);
            $this->perform(Event::ERROR, new Meta(when: Action::PROCESS, info: $error));
        } elseif ($numChangedStreams > 0) {
            foreach ($readStreams as $stream) {
                if ($stream === $this->_connection['in']) {
                    // Handle standard input
                    $this->handleInput($stream);
                } elseif ($stream === $this->_process->pipes(1)) {
                    // Handle process output
                    $this->handleProcessOutput($stream);
                }
            }
        }
    }

    /**
     * Handles input from standard input.
     *
     * @param resource $stream The input stream.
     * @return void
     */
    private function handleInput($stream)
    {
        $data = fgets($stream);

        if ($data !== false) {
            $this->_result .= $data;
            $this->dispatch(Event::RECEIVED, new Meta(data: $data));
        } else {
            $error = "No data received from input before EOF";
            error_log('IO Error: ' . $error);
            $this->perform(Event::ERROR, new Meta(when: Action::PROCESS, info: $error));
        }
    }

    /**
     * Handles output from the process.
     *
     * @param resource $stream The output stream.
     * @return void
     */
    private function handleProcessOutput($stream)
    {
        $data = fread($stream, 8192);

        if ($data !== false && $data !== "") {
            $this->dispatch(Event::RECEIVED, new Meta(data: $data, when: 'OnSystemUpdate'));
        } elseif (feof($stream)) {
            $error = "Reached EOF for process output";
            // error_log('IO Notice: ' . $error);
            $this->dispatch(Event::RECEIVED, new Meta(data: $data, when: 'OnSystemUpdate'));
        } else {
            $error = "No data received from process output";
            error_log('IO Error: ' . $error);
            $this->perform(Event::ERROR, new Meta(when: Action::PROCESS, info: $error));
        }
    }

    /**
     * Continuously runs the input listener and handles notifications.
     * 
     * @return void
     */
    public function run()
    {
        $start_time = time();
        $notification_interval = 5; // Interval in seconds to show notifications

        while (true) {
            $this->listen();

            // Check if it's time to show a notification
            if (time() - $start_time >= $notification_interval) {
                $this->send("Notification: " . date('H:i:s') . "\n");
                $start_time = time();
            }

            usleep(100000); // 0.1 seconds
        }
    }
}
