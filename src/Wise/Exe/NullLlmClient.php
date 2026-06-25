<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Obj;

class NullLlmClient extends Obj implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('', false);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        return $this->generate($input, $config);
    }

    public function respond($input, $config = []): Reply
    {
        return $this->generate($input, $config);
    }
}
