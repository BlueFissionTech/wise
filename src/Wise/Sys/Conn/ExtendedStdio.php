<?php

namespace BlueFission\Wise\Sys\Conn;

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
    private $_pollProcess;
    private $_pollingScript;
    private $_stdinProcess;
    private $_stdinScript;
    private bool $_useStdinProxy = false;

    /**
     * Constructor that sets the configuration data.
     *
     * @param array|null $config Configuration data.
     */
    public function __construct(string $stdinScript = null, string $pollingScript = null, $config = null)
    {
        parent::__construct($config);
        $this->_pollingScript = $pollingScript;
        $this->_stdinScript = $stdinScript;
        $this->initProcess();
    }

    /**
     * Initializes the child process to handle input.
     */
    private function initProcess()
    {
        if ( !$this->_pollingScript ) {
            $this->_pollingScript = 'php';
        }
     
        $this->_pollProcess = new Process($this->_pollingScript, null, null, [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "a"],  // stderr
        ]);

        $this->_pollProcess->start();

        stream_set_blocking($this->_pollProcess->pipes(1), false);
        stream_set_blocking($this->_pollProcess->pipes(2), false);

        if ( PHP_OS == 'Linux' ) {
            system('stty cbreak');
            system('stty -icanon');
            $echo = getenv('WISE_TTY_ECHO');
            if ($echo === '0') {
                system('stty -echo');
            } elseif ($echo !== false) {
                system('stty echo');
            }

            register_shutdown_function(static function (): void {
                if (PHP_OS === 'Linux') {
                    system('stty echo');
                    system('stty icanon');
                }
            });
        } elseif ( PHP_OS == 'WINNT' ) {
            $this->_useStdinProxy = getenv('WISE_STDIN_PROXY') === '1';
            if ($this->_useStdinProxy) {
                // Optional stdin proxy process for Windows (disabled by default).
                if (! $this->_stdinScript ) {
                    $this->_stdinScript = 'php';
                }

                $this->_stdinProcess = new Process($this->_stdinScript, null, null, [
                    0 => ["pipe", "r"],  // stdin
                    1 => ["pipe", "w"],  // stdout
                    2 => ["pipe", "a"],  // stderr
                ]);

                $this->_stdinProcess->start();

                stream_set_blocking($this->_stdinProcess->pipes(1), false);
            }
        }

        if (PHP_OS == 'WINNT') {
            $blocking = filter_var(getenv('WISE_STDIN_BLOCKING') ?: '1', FILTER_VALIDATE_BOOLEAN);
            stream_set_blocking(\STDIN, $blocking);
        } else {
            stream_set_blocking(\STDIN, false);
        }
    }

    protected function _open(): void
    {
        parent::_open();

        if (PHP_OS === 'WINNT') {
            $blocking = filter_var(getenv('WISE_STDIN_BLOCKING') ?: '1', FILTER_VALIDATE_BOOLEAN);
            if (isset($this->_connection['in']) && is_resource($this->_connection['in'])) {
                stream_set_blocking($this->_connection['in'], $blocking);
            }
            stream_set_blocking(\STDIN, $blocking);
        }
    }

    /**
     * Continuously reads data from standard input in a non-blocking way.
     * 
     * @return void
     */
    protected function listen()
    {
        $stdin = $this->_connection['in'] ?? STDIN;
        if ($this->_useStdinProxy && $this->_stdinProcess) {
            $stdin = $this->_stdinProcess->pipes(1);
        }

        $this->_result = "";

        if (PHP_OS == 'WINNT') {
            $this->handleInput($stdin);
            $this->handlePollingOutput($this->_pollProcess->pipes(1));
            return;
        }

        $readStreams = [$stdin, $this->_pollProcess->pipes(1)];
        $writeStreams = null;
        $exceptStreams = null;
        $timeout = 0; 

        $numChangedStreams = stream_select($readStreams, $writeStreams, $exceptStreams, $timeout);

        if ($numChangedStreams === false) {
            // Error occurred during stream_select
            $error = "stream_select error";
            error_log('IO Error: ' . $error);
            $this->perform(Event::ERROR, new Meta(when: Action::PROCESS, info: $error));
        } elseif ($numChangedStreams > 0) {
            foreach ($readStreams as $stream) {
                if ($stream === $stdin) {
                    // Handle standard input
                    $this->handleInput($stream);
                } elseif ($stream === $this->_pollProcess->pipes(1)) {
                    // Handle process output
                    $this->handlePollingOutput($stream);
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
        // Check if the stream is valid
        if (!is_resource($stream) || feof($stream)) {
            $error = "Invalid or closed input stream";
            error_log('IO Error: ' . $error);
            $this->perform(Event::ERROR, new Meta(when: Action::PROCESS, info: $error));
            return;
        }

        if (PHP_OS === 'WINNT' && !$this->_useStdinProxy) {
            $data = $this->readWindowsInput($stream);
        } else {
            $data = stream_get_line($stream, 1);
        }

        // die($data);

        if ($data !== false && $data !== '') {
            $this->_result .= $data;
            $this->dispatch(Event::RECEIVED, new Meta(data: $data));
        } else {
            if (feof($stream)) {
                return;
            }
            return;
        }
    }

    /**
     * Handles output from the process.
     *
     * @param resource $stream The output stream.
     * @return void
     */
    private function handlePollingOutput($stream)
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

    private function readWindowsInput($stream)
    {
        $meta = stream_get_meta_data($stream);
        $blocked = $meta['blocked'] ?? null;

        if ($blocked) {
            return fgets($stream);
        }

        $data = fread($stream, 8192);
        return $data === '' ? false : $data;
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
