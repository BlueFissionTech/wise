<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class ContinuationState extends Obj
{
    private Str $token;
    private Arr $command;
    private Arr $metadata;
    private Arr $scope;
    private Num $expiresAt;

    public function __construct(
        string $token,
        array $command,
        array $metadata,
        array $scope,
        float $expiresAt
    ) {
        parent::__construct();
        $this->token = Str::make($token)->trim();
        $this->command = Arr::make($command);
        $this->metadata = Arr::make($metadata);
        $this->scope = Arr::make($scope);
        $this->expiresAt = Num::make($expiresAt);
    }

    public static function issue(array $command, array $metadata, int $ttlSeconds = 900): self
    {
        return new self(
            Str::uuid4(),
            $command,
            $metadata,
            self::scopeFromContext($metadata),
            microtime(true) + Num::max(1, $ttlSeconds)
        );
    }

    public static function fromArray(array $state): self
    {
        return new self(
            (string)($state['token'] ?? ''),
            Arr::is($state['command'] ?? null) ? $state['command'] : [],
            Arr::is($state['metadata'] ?? null) ? $state['metadata'] : [],
            Arr::is($state['scope'] ?? null) ? $state['scope'] : [],
            (float)($state['expires_at'] ?? 0)
        );
    }

    public static function scopeFromContext(array $context): array
    {
        $scope = Arr::make();
        $actor = Arr::is($context['actor'] ?? null) ? $context['actor'] : [];
        $values = [
            'actor_id' => $actor['id'] ?? null,
            'agent_id' => $context['agent_id'] ?? null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'profile_id' => $context['profile_id'] ?? null,
        ];
        foreach ($values as $key => $value) {
            if (Val::isNotEmpty($value)) {
                $scope[$key] = (string)$value;
            }
        }

        return $scope->toArray();
    }

    public function token(): string
    {
        return $this->token->val();
    }

    public function command(): array
    {
        return $this->command->toArray();
    }

    public function metadata(): array
    {
        return $this->metadata->toArray();
    }

    public function matches(array $scope): bool
    {
        return $this->scope->toArray() === $scope;
    }

    public function expired(?float $now = null): bool
    {
        return ($now ?? microtime(true)) >= $this->expiresAt->val();
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token(),
            'command' => $this->command(),
            'metadata' => $this->metadata(),
            'scope' => $this->scope->toArray(),
            'expires_at' => $this->expiresAt->val(),
        ];
    }
}
