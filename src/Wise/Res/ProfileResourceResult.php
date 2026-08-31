<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Obj;
use BlueFission\Str;

final class ProfileResourceResult extends Obj
{
    private Flag $successful;
    private Str $status;
    private mixed $data;
    private Arr $diagnostics;
    private Arr $metadata;

    public function __construct(
        bool $successful,
        string $status,
        mixed $data = null,
        array $diagnostics = [],
        array $metadata = []
    ) {
        parent::__construct();
        $this->successful = Flag::make($successful);
        $this->status = Str::make($status)->trim()->lower();
        $this->data = $data;
        $this->diagnostics = Arr::make($diagnostics);
        $this->metadata = Arr::make($metadata);
    }

    public static function success(string $status, mixed $data = null, array $metadata = []): self
    {
        return new self(true, $status, $data, metadata: $metadata);
    }

    public static function failure(string $status, array $diagnostics, array $metadata = []): self
    {
        return new self(false, $status, diagnostics: $diagnostics, metadata: $metadata);
    }

    public function successful(): bool
    {
        return $this->successful->isTruthy();
    }

    public function status(): string
    {
        return $this->status->val();
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function diagnostics(): array
    {
        return $this->diagnostics->toArray();
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }

    public function toArray(): array
    {
        return [
            'success' => $this->successful(),
            'status' => $this->status(),
            'data' => $this->data(),
            'diagnostics' => $this->diagnostics(),
            'metadata' => $this->metadata(),
        ];
    }
}
