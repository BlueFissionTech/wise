<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Cmd\RuntimeContext;

final class FunctionResource extends ProfileResource
{
    private mixed $invoker;

    public function __construct(
        IProfileResourceStore $store,
        ProfileScope $scope,
        ?callable $invoker = null
    ) {
        parent::__construct($store, $scope, 'function');
        $this->invoker = $invoker;
    }

    protected function actionDefinitions(): array
    {
        return [
            'list' => $this->definition('List private profile functions', 'wise.profile.function.read'),
            'get' => $this->definition('Get a private profile function', 'wise.profile.function.read'),
            'create' => $this->definition('Create a private profile function', 'wise.profile.function.write'),
            'update' => $this->definition('Update a private profile function', 'wise.profile.function.write'),
            'delete' => $this->definition(
                'Delete a private profile function',
                'wise.profile.function.write',
                true
            ),
            'invoke' => $this->definition(
                'Invoke a private profile function',
                'wise.profile.function.invoke'
            ),
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
            'get' => $this->getFunction($id, $metadata),
            'create' => $this->createFunction($id, $payload, $metadata),
            'update' => $this->updateFunction($id, $payload, $metadata),
            'delete' => $this->deleteFunction($id, $metadata),
            'invoke' => $this->invokeFunction($id, $payload, $context, $metadata),
            default => ProfileResourceResult::failure('invalid', ['resource_action_unknown'], $metadata),
        };
    }

    private function getFunction(string $id, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);

        return Arr::is($record)
            ? ProfileResourceResult::success('found', $record, $metadata)
            : $this->notFound($id, $metadata);
    }

    private function createFunction(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        if (Arr::is($this->record($id))) {
            return ProfileResourceResult::failure('conflict', ['resource_exists', 'id' => $id], $metadata);
        }

        $handler = Str::make((string)($payload['handler'] ?? ''))->trim()->val();
        if (Str::isEmpty($handler)) {
            return ProfileResourceResult::failure('invalid', ['function_handler_required'], $metadata);
        }

        $now = $this->timestamp();
        $record = [
            'id' => $id,
            'name' => (string)($payload['name'] ?? $id),
            'description' => (string)($payload['description'] ?? ''),
            'handler' => $handler,
            'parameters' => Arr::is($payload['parameters'] ?? null) ? $payload['parameters'] : [],
            'required_capabilities' => $this->capabilities($payload),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->save($id, $record);

        return ProfileResourceResult::success('created', $record, $metadata);
    }

    private function updateFunction(string $id, array $payload, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }

        foreach (['name', 'description', 'handler', 'parameters'] as $field) {
            if (Val::is($payload[$field] ?? null)) {
                $record[$field] = $payload[$field];
            }
        }
        if (Arr::hasKey($payload, 'required_capabilities')) {
            $record['required_capabilities'] = $this->capabilities($payload);
        }
        $record['updated_at'] = $this->timestamp();
        $this->save($id, $record);

        return ProfileResourceResult::success('updated', $record, $metadata);
    }

    private function deleteFunction(string $id, array $metadata): ProfileResourceResult
    {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }

        return $this->remove($id)
            ? ProfileResourceResult::success('deleted', ['id' => $id], $metadata)
            : $this->notFound($id, $metadata);
    }

    private function invokeFunction(
        string $id,
        array $payload,
        RuntimeContext $context,
        array $metadata
    ): ProfileResourceResult {
        if (Str::isEmpty($id)) {
            return $this->missingId($metadata);
        }
        $record = $this->record($id);
        if (!Arr::is($record)) {
            return $this->notFound($id, $metadata);
        }
        foreach ($record['required_capabilities'] ?? [] as $capability) {
            if (!$context->hasCapability((string)$capability)) {
                return ProfileResourceResult::failure(
                    'denied',
                    ['capability_denied', 'capability' => $capability],
                    $metadata
                );
            }
        }
        if (!Func::isCallable($this->invoker)) {
            return ProfileResourceResult::failure('unavailable', ['function_invoker_unavailable'], $metadata);
        }

        try {
            $result = ($this->invoker)($record, $payload['arguments'] ?? [], $context);
        } catch (\Throwable $exception) {
            return ProfileResourceResult::failure(
                'failed',
                ['function_invocation_failed', 'exception' => $exception::class],
                $metadata
            );
        }

        return $result instanceof ProfileResourceResult
            ? $result
            : ProfileResourceResult::success('invoked', ['result' => $result], $metadata);
    }

    private function capabilities(array $payload): array
    {
        $capabilities = Arr::is($payload['required_capabilities'] ?? null)
            ? $payload['required_capabilities']
            : [];

        return Arr::make($capabilities)
            ->map(fn ($capability) => Str::make((string)$capability)->trim()->lower()->val())
            ->filter(fn ($capability) => Str::isNotEmpty((string)$capability))
            ->unique()
            ->sort()
            ->toArray();
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
