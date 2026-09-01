<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Cmd\RuntimeContext;

final class ProfileStepResource extends ProfileResource
{
    private const STATUSES = ['pending', 'active', 'completed', 'blocked', 'cancelled'];

    public function __construct(IProfileResourceStore $store, ProfileScope $scope)
    {
        parent::__construct($store, $scope, 'profile_step');
    }

    protected function actionDefinitions(): array
    {
        return [
            'list' => $this->definition('List private profile steps', 'wise.profile.step.read'),
            'get' => $this->definition('Get a private profile step', 'wise.profile.step.read'),
            'create' => $this->definition('Create a private profile step', 'wise.profile.step.write'),
            'update' => $this->definition('Update a private profile step', 'wise.profile.step.write'),
            'transition' => $this->definition('Transition a private profile step', 'wise.profile.step.write'),
            'delete' => $this->definition('Delete a private profile step', 'wise.profile.step.write', true),
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
            'list' => $this->listSteps($payload, $metadata),
            'get' => $this->getStep($id, $metadata),
            'create' => $this->createStep($id, $payload, $metadata),
            'update' => $this->updateStep($id, $payload, $metadata),
            'transition' => $this->transitionStep($id, $payload, $metadata),
            'delete' => $this->deleteStep($id, $metadata),
            default => ProfileResourceResult::failure('invalid', ['resource_action_unknown'], $metadata),
        };
    }

    private function listSteps(array $payload, array $metadata): ProfileResourceResult
    {
        $goalId = Str::make((string)($payload['goal_id'] ?? ''))->trim()->lower()->val();
        $records = Arr::make($this->records())
            ->filter(fn (array $record) => Str::isEmpty($goalId)
                || Str::match($goalId, (string)($record['goal_id'] ?? '')))
            ->sort(fn (array $left, array $right): int => ($left['position'] ?? 0) <=> ($right['position'] ?? 0))
            ->toArray();

        return ProfileResourceResult::success('listed', Arr::values($records), $metadata);
    }

    private function getStep(string $id, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);

        return Arr::is($record)
            ? ProfileResourceResult::success('found', $record, $metadata)
            : $this->notFound($id, $metadata);
    }

    private function createStep(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        if (Arr::is($this->record($id))) {
            return ProfileResourceResult::failure('conflict', ['resource_exists', 'id' => $id], $metadata);
        }

        $goalId = Str::make((string)($payload['goal_id'] ?? ''))->trim()->lower()->val();
        if (Str::isEmpty($goalId)
            || !Arr::is($this->store->get($this->scope, 'goal', $goalId))) {
            return ProfileResourceResult::failure('invalid', ['goal_not_found', 'goal_id' => $goalId], $metadata);
        }

        $now = $this->timestamp();
        $record = [
            'id' => $id,
            'goal_id' => $goalId,
            'description' => (string)($payload['description'] ?? ''),
            'position' => Num::max(0, (int)($payload['position'] ?? Arr::size($this->records()))),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->save($id, $record);

        return ProfileResourceResult::success('created', $record, $metadata);
    }

    private function updateStep(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }

        if (Val::is($payload['description'] ?? null)) {
            $record['description'] = (string)$payload['description'];
        }
        if (Val::is($payload['position'] ?? null)) {
            $record['position'] = Num::max(0, (int)$payload['position']);
        }
        $record['updated_at'] = $this->timestamp();
        $this->save($id, $record);

        return ProfileResourceResult::success('updated', $record, $metadata);
    }

    private function transitionStep(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }

        $status = Str::make((string)($payload['status'] ?? ''))->trim()->lower()->val();
        if (!Arr::has(self::STATUSES, $status, true)) {
            return ProfileResourceResult::failure(
                'invalid',
                ['step_status_invalid', 'status' => $status],
                $metadata
            );
        }

        $record['status'] = $status;
        $record['updated_at'] = $this->timestamp();
        $this->save($id, $record);

        return ProfileResourceResult::success('transitioned', $record, $metadata);
    }

    private function deleteStep(string $id, array $metadata): ProfileResourceResult
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
