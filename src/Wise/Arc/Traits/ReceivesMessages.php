<?php

namespace BlueFission\Wise\Arc\Traits;

trait ReceivesMessages {
    private function poll() {
        $this->_async::do([$this, 'getMessages'])->then((function($response) {
            $this->send("Notification: " . date('H:i:s') . "\n");
        })->bindTo($this, $this), function($error) {
            // Handle this somehow
        })->try();
    }

    public function getMessages( $resolve ) {
        $notification_interval = 5;
        $start_time = time();

        while (true) {
            // Check if it's time to show a notification
            if (time() - $start_time >= $notification_interval) {
                $resolve();
                return;
            }

            sleep(1); // 0.1 seconds
        }
    }
}