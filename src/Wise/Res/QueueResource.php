<?php

// QueueCommand.php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Data\Queues\Queue as Queue;

class QueueResource extends Service
{
    protected $queueName;

    public function __construct()
    {
        parent::__construct();
        $this->queueName = '_system_queue';
    }

    public function handle($behavior, $args)
    {
        $action = $behavior->name();

        Queue::setMode(Queue::FIFO);
        if ($action == 'add') {
            foreach ($args as $arg) {
                Queue::enqueue($this->queueName, $arg);
            }
            $this->_response = "Added to queue.";
        } elseif ($action == 'get') {
            $this->_response = Queue::dequeue($this->queueName);
        }
    }
}
