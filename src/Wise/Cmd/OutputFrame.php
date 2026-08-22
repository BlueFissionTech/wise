<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

final class OutputFrame extends Obj
{
    public const OUTPUT = 'output';
    public const DIAGNOSTIC = 'diagnostic';
    public const STATUS = 'status';
    public const PROMPT = 'prompt';
    public const PROGRESS = 'progress';

    private Str $type;
    private mixed $payload;
    private Arr $metadata;

    public function __construct(string $type, mixed $payload, array $metadata = [])
    {
        parent::__construct();
        $this->type = Str::make($type)->trim()->lower();
        $this->payload = $payload;
        $this->metadata = Arr::make($metadata);
    }

    public function type(): string
    {
        return $this->type->val();
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'payload' => $this->payload(),
            'metadata' => $this->metadata(),
        ];
    }
}
