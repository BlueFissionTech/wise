<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

class CommandRequest extends Obj
{
    public const EXECUTE = 'execute';
    public const PARSE_ONLY = 'parse';

    private Command|array|string $input;
    private Str $mode;
    private Arr $context;
    private ?Str $continuationToken;
    private ?bool $approved;

    public function __construct(
        Command|array|string $input,
        string $mode = self::EXECUTE,
        array $context = [],
        ?string $continuationToken = null,
        ?bool $approved = null
    ) {
        parent::__construct();
        $this->input = $input;
        $this->mode = Str::make($mode)->trim()->lower();
        $this->context = Arr::make($context);
        $this->continuationToken = Str::isNotEmpty((string)$continuationToken)
            ? Str::make((string)$continuationToken)->trim()
            : null;
        $this->approved = $approved;
    }

    public static function parse(Command|array|string $input, array $context = []): self
    {
        return new self($input, self::PARSE_ONLY, $context);
    }

    public static function resume(string $continuationToken, bool $approved, array $context = []): self
    {
        return new self('', self::EXECUTE, $context, $continuationToken, $approved);
    }

    public function input(): Command|array|string
    {
        return $this->input;
    }

    public function mode(): string
    {
        return $this->mode->val();
    }

    public function shouldExecute(): bool
    {
        return $this->mode() !== self::PARSE_ONLY;
    }

    public function context(): array
    {
        return $this->context->toArray();
    }

    public function isContinuation(): bool
    {
        return $this->continuationToken !== null && $this->approved !== null;
    }

    public function continuationToken(): ?string
    {
        return $this->continuationToken?->val();
    }

    public function approved(): ?bool
    {
        return $this->approved;
    }
}
