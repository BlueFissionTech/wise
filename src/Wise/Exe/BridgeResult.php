<?php

namespace BlueFission\Wise\Exe;

class BridgeResult
{
    private bool $success;
    private string $output;
    private array $meta;

    public function __construct(bool $success, string $output = '', array $meta = [])
    {
        $this->success = $success;
        $this->output = $output;
        $this->meta = $meta;
    }

    public static function success(string $output = '', array $meta = []): self
    {
        return new self(true, $output, $meta);
    }

    public static function failure(string $output = '', array $meta = []): self
    {
        return new self(false, $output, $meta);
    }

    public function successFlag(): bool
    {
        return $this->success;
    }

    public function output(): string
    {
        return $this->output;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
