<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use InvalidArgumentException;

final class ProfileScope extends Obj
{
    private Str $tenantId;
    private Str $applicationId;
    private Str $profileId;
    private Str $ownerId;
    private Str $ownerType;

    public function __construct(
        string $tenantId,
        string $applicationId,
        string $profileId,
        string $ownerId,
        string $ownerType
    ) {
        parent::__construct();

        $values = Arr::make([
            'tenant_id' => $tenantId,
            'application_id' => $applicationId,
            'profile_id' => $profileId,
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
        ])->map(fn ($value) => Str::make((string)$value)->trim()->lower()->val());

        foreach ($values as $field => $value) {
            if (Str::isEmpty((string)$value)) {
                throw new InvalidArgumentException("Profile scope requires {$field}.");
            }
        }

        $this->tenantId = Str::make((string)$values['tenant_id']);
        $this->applicationId = Str::make((string)$values['application_id']);
        $this->profileId = Str::make((string)$values['profile_id']);
        $this->ownerId = Str::make((string)$values['owner_id']);
        $this->ownerType = Str::make((string)$values['owner_type']);
    }

    public static function fromContext(array $context): self
    {
        $actor = Arr::is($context['actor'] ?? null) ? $context['actor'] : [];
        $profileId = (string)($context['profile_id'] ?? $actor['profile_id'] ?? $actor['id'] ?? '');

        return new self(
            (string)($context['tenant_id'] ?? $actor['tenant_id'] ?? ''),
            (string)($context['application_id'] ?? $actor['application_id'] ?? ''),
            $profileId,
            (string)($context['owner_id'] ?? $actor['owner_id'] ?? $actor['id'] ?? $profileId),
            (string)($context['owner_type'] ?? $actor['owner_type'] ?? $actor['type'] ?? '')
        );
    }

    public function key(): string
    {
        return sha1(Arr::make($this->toArray())->join('|')->val());
    }

    public function matches(self $other): bool
    {
        return Str::match($this->key(), $other->key());
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId->val(),
            'application_id' => $this->applicationId->val(),
            'profile_id' => $this->profileId->val(),
            'owner_id' => $this->ownerId->val(),
            'owner_type' => $this->ownerType->val(),
        ];
    }
}
