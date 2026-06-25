<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Jenerator\Runtime\Io\IoInterface;
use BlueFission\Obj;

class JenssIo extends Obj implements IoInterface
{
    private BridgeContext $context;
    private string $channel = 'default';
    private string $inputChannel = 'default';
    /** @var array<int, string> */
    private array $messages = [];
    /** @var array<int, string> */
    private array $prompts = [];

    public function __construct(BridgeContext $context)
    {
        parent::__construct();
        $this->context = $context;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
        $this->dispatch(Event::CHANGE, new Meta(data: ['channel' => $channel], src: $this));
    }

    public function setInputChannel(string $channel): void
    {
        $this->inputChannel = $channel;
        $this->dispatch(Event::CHANGE, new Meta(data: ['inputChannel' => $channel], src: $this));
    }

    public function say(string $message): void
    {
        $this->messages[] = $message;
        $this->context->output($message);
        $this->dispatch(Event::SENT, new Meta(data: ['message' => $message], src: $this));
    }

    public function prompt(string $message): string
    {
        $this->prompts[] = $message;
        $response = $this->context->prompt($message);
        $this->dispatch(Event::MESSAGE, new Meta(data: [
            'message' => $message,
            'response' => $response,
        ], src: $this));
        return $response;
    }

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, string>
     */
    public function prompts(): array
    {
        return $this->prompts;
    }

    public function channel(): string
    {
        return $this->channel;
    }
}
