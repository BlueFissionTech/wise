<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Cmd\RuntimeContext;

final class GoalResource extends ProfileResource
{
    private const TRANSITIONS = [
        'planned' => ['active', 'cancelled'],
        'active' => ['blocked', 'completed', 'cancelled'],
        'blocked' => ['active', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(IProfileResourceStore $store, ProfileScope $scope)
    {
        parent::__construct($store, $scope, 'goal');
    }

    protected function actionDefinitions(): array
    {
        return [
            'list' => $this->definition('List private profile goals', 'wise.profile.goal.read'),
            'get' => $this->definition('Get a private profile goal', 'wise.profile.goal.read'),
            'create' => $this->definition('Create a private profile goal', 'wise.profile.goal.write'),
            'update' => $this->definition('Update a private profile goal', 'wise.profile.goal.write'),
            'transition' => $this->definition('Transition a private profile goal', 'wise.profile.goal.write'),
            'delete' => $this->definition('Delete a private profile goal', 'wise.profile.goal.write', true),
        ];
    }

    protected function dispatchAction(
        string $action,
        string $id,
        array $payload,
        RuntimeContext $context,
        array $metadata
    ): ProfileResourceResult {
        return match ($action) {
            'list' => ProfileResourceResult::success('listed', Arr::values($this->records()), $metadata),
            'get' => $this->getGoal($id, $metadata),
            'create' => $this->createGoal($id, $payload, $metadata),
            'update' => $this->updateGoal($id, $payload, $metadata),
            'transition' => $this->transitionGoal($id, $payload, $metadata),
            'delete' => $this->deleteGoal($id, $metadata),
            default => ProfileResourceResult::failure('invalid', ['resource_action_unknown'], $metadata),
        };
    }

    private function getGoal(string $id, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);

        return Arr::is($record)
            ? ProfileResourceResult::success('found', $record, $metadata)
            : $this->notFound($id, $metadata);
    }

    private function createGoal(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        if (Arr::is($this->record($id))) {
            return ProfileResourceResult::failure('conflict', ['resource_exists', 'id' => $id], $metadata);
        }

        $now = $this->timestamp();
        $record = [
            'id' => $id,
            'title' => (string)($payload['title'] ?? $id),
            'description' => (string)($payload['description'] ?? ''),
            'status' => 'planned',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->save($id, $record);

        return ProfileResourceResult::success('created', $record, $metadata);
    }

    private function updateGoal(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }

        foreach (['title', 'description'] as $field) {
            if (Val::is($payload[$field] ?? null)) {
                $record[$field] = (string)$payload[$field];
            }
        }
        $record['updated_at'] = $this->timestamp();
        $this->save($id, $record);

        return ProfileResourceResult::success('updated', $record, $metadata);
    }

    private function transitionGoal(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }

        $next = Str::make((string)($payload['status'] ?? ''))->trim()->lower()->val();
        $allowed = self::TRANSITIONS[$record['status']] ?? [];
        if (!Arr::has($allowed, $next, true)) {
            return ProfileResourceResult::failure(
                'invalid',
                ['goal_transition_invalid', 'from' => $record['status'], 'to' => $next],
                $metadata
            );
        }

        $record['status'] = $next;
        $record['updated_at'] = $this->timestamp();
        $this->save($id, $record);

        return ProfileResourceResult::success('transitioned', $record, $metadata);
    }

    private function deleteGoal(string $id, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }

        return $this->remove($id)
            ? ProfileResourceResult::success('deleted', ['id' => $id], $metadata)
            : $this->notFound($id, $metadata);
    }

    private function definition(string $summary, string $capability, bool $confirmation = false): array
    {
        return [
            'summary' => $summary,
            'argument_shape' => ['type' => 'object', 'additional_properties' => true],
            'confirmation_required' => $confirmation,
            'required_capabilities' => [$capability],
            'capability' => $capability,
        ];
    }
}
