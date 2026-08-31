<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Obj;
use BlueFission\Str;

final class ContinuationClaim extends Obj
{
    public const ACCEPTED = 'accepted';
    public const MISSING = 'missing';
    public const EXPIRED = 'expired';
    public const MISMATCHED = 'mismatched';
    public const REPLAYED = 'replayed';

    private Str $status;

    public function __construct(string $status, private ?ContinuationState $state = null)
    {
        parent::__construct();
        $this->status = Str::make($status)->trim()->lower();
    }

    public function status(): string
    {
        return $this->status->val();
    }

    public function accepted(): bool
    {
        return Str::match(self::ACCEPTED, $this->status());
    }

    public function state(): ?ContinuationState
    {
        return $this->state;
    }
}
