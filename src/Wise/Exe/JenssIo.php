<?php

namespace BlueFission\Wise\Exe;

use BlueFission\Jenerate\Runtime\Io\IoInterface;

class JenssIo implements IoInterface
{
    private BridgeContext $context;
    private string $channel = 'default';
    /** @var array<int, string> */
    private array $messages = [];
    /** @var array<int, string> */
    private array $prompts = [];

    public function __construct(BridgeContext $context)
    {
        $this->context = $context;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function say(string $message): void
    {
        $this->messages[] = $message;
        $this->context->output($message);
    }

    public function prompt(string $message): string
    {
        $this->prompts[] = $message;
        return $this->context->prompt($message);
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
