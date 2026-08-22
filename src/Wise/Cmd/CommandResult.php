<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

class CommandResult extends Obj
{
    public const COMPLETED = 'completed';
    public const PARSED = 'parsed';
    public const CONFIRMATION_REQUIRED = 'confirmation_required';
    public const INVALID = 'invalid';
    public const FAILED = 'failed';

    private Str $status;
    private mixed $output;
    private ?Command $command;
    private Str $description;
    private bool $confirmationRequired;
    private int $exitCode;
    private Arr $diagnostics;
    private Arr $metadata;
    private ?Str $continuationToken;

    public function __construct(
        string $status,
        mixed $output = null,
        ?Command $command = null,
        bool $confirmationRequired = false,
        int $exitCode = 0,
        array $diagnostics = [],
        array $metadata = [],
        ?string $continuationToken = null
    ) {
        parent::__construct();
        $this->status = Str::make($status)->trim()->lower();
        $this->output = $output;
        $this->command = $command;
        $this->description = Str::make($command?->description() ?? '')->trim();
        $this->confirmationRequired = $confirmationRequired;
        $this->exitCode = $exitCode;
        $this->diagnostics = Arr::make($diagnostics);
        $this->metadata = Arr::make($metadata);
        $this->continuationToken = Str::isNotEmpty((string)$continuationToken)
            ? Str::make((string)$continuationToken)->trim()
            : null;
    }

    public static function completed(mixed $output, ?Command $command = null, array $metadata = []): self
    {
        return new self(self::COMPLETED, $output, $command, false, 0, [], $metadata);
    }

    public static function parsed(Command $command, array $metadata = []): self
    {
        return new self(self::PARSED, null, $command, false, 0, [], $metadata);
    }

    public static function pending(
        mixed $output,
        ?Command $command,
        string $continuationToken,
        array $metadata = []
    ): self
    {
        return new self(
            self::CONFIRMATION_REQUIRED,
            $output,
            $command,
            true,
            0,
            [],
            $metadata,
            $continuationToken
        );
    }

    public static function invalid(
        string $message,
        array $diagnostics = [],
        array $metadata = [],
        ?Command $command = null
    ): self
    {
        return new self(self::INVALID, $message, $command, false, 2, $diagnostics, $metadata);
    }

    public static function failure(string $message, array $diagnostics = [], array $metadata = []): self
    {
        return new self(self::FAILED, $message, null, false, 1, $diagnostics, $metadata);
    }

    public function status(): string
    {
        return $this->status->val();
    }

    public function successful(): bool
    {
        return Arr::has([self::COMPLETED, self::PARSED], $this->status(), true);
    }

    public function output(): mixed
    {
        return $this->output;
    }

    public function command(): ?Command
    {
        return $this->command;
    }

    public function description(): string
    {
        return $this->description->val();
    }

    public function getDescription(): string
    {
        return $this->description();
    }

    public function confirmationRequired(): bool
    {
        return $this->confirmationRequired;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function diagnostics(): array
    {
        return $this->diagnostics->toArray();
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }

    public function continuationToken(): ?string
    {
        return $this->continuationToken?->val();
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->status(),
            $this->output(),
            $this->command(),
            $this->confirmationRequired(),
            $this->exitCode(),
            $this->diagnostics(),
            Arr::merge($metadata, $this->metadata()),
            $this->continuationToken()
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status(),
            'output' => $this->output(),
            'command' => $this->command?->toArray(),
            'description' => $this->description(),
            'confirmation_required' => $this->confirmationRequired(),
            'exit_code' => $this->exitCode(),
            'diagnostics' => $this->diagnostics(),
            'metadata' => $this->metadata(),
            'continuation_token' => $this->continuationToken(),
        ];
    }
}
