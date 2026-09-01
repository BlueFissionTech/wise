<?php

namespace BlueFission\Wise\Res;

use BlueFission\Arr;
use BlueFission\Date;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Cmd\CommandDescriptor;
use BlueFission\Wise\Cmd\RuntimeContext;
use InvalidArgumentException;

abstract class ProfileResource extends Obj
{
    protected Str $resource;

    public function __construct(
        protected IProfileResourceStore $store,
        protected ProfileScope $scope,
        string $resource
    ) {
        parent::__construct();
        $this->resource = Str::make($resource)->trim()->lower();
    }

    public function descriptors(RuntimeContext $context): array
    {
        if (!$this->scopeAllowed($context)) {
            return [];
        }

        $descriptors = Arr::make();
        foreach ($this->actionDefinitions() as $action => $definition) {
            $capability = (string)($definition['capability'] ?? '');
            if (Str::isNotEmpty($capability) && !$context->hasCapability($capability)) {
                continue;
            }

            $descriptors->push(CommandDescriptor::resource(
                $action . ' ' . $this->resource->val(),
                $definition
            )->toArray());
        }

        return $descriptors->sort(
            fn (array $left, array $right): int => $left['identifier'] <=> $right['identifier']
        )->toArray();
    }

    public function execute(
        string $action,
        ?string $id,
        array $payload,
        RuntimeContext $context
    ): ProfileResourceResult {
        $action = Str::make($action)->trim()->lower()->val();
        $metadata = $this->resultMetadata($context, $action);
        if (!$this->scopeAllowed($context)) {
            return ProfileResourceResult::failure(
                'denied',
                ['profile_scope_denied'],
                $metadata
            );
        }

        $definitions = $this->actionDefinitions();
        if (!Arr::hasKey($definitions, $action)) {
            return ProfileResourceResult::failure(
                'invalid',
                ['resource_action_unknown', 'action' => $action],
                $metadata
            );
        }

        $capability = (string)($definitions[$action]['capability'] ?? '');
        if (Str::isNotEmpty($capability) && !$context->hasCapability($capability)) {
            return ProfileResourceResult::failure(
                'denied',
                ['capability_denied', 'capability' => $capability],
                $metadata
            );
        }

        return $this->dispatchAction($action, $this->normalizeId($id), $payload, $context, $metadata);
    }

    abstract protected function actionDefinitions(): array;

    abstract protected function dispatchAction(
        string $action,
        string $id,
        array $payload,
        RuntimeContext $context,
        array $metadata
    ): ProfileResourceResult;

    protected function records(): array
    {
        return $this->store->all($this->scope, $this->resource->val());
    }

    protected function record(string $id): ?array
    {
        return Str::isEmpty($id)
            ? null
            : $this->store->get($this->scope, $this->resource->val(), $id);
    }

    protected function save(string $id, array $record): void
    {
        $this->store->put($this->scope, $this->resource->val(), $id, $record);
    }

    protected function remove(string $id): bool
    {
        return $this->store->delete($this->scope, $this->resource->val(), $id);
    }

    protected function timestamp(): string
    {
        return (string)Date::now()->format('c');
    }

    protected function missingId(array $metadata): ProfileResourceResult
    {
        return ProfileResourceResult::failure('invalid', ['resource_id_required'], $metadata);
    }

    protected function notFound(string $id, array $metadata): ProfileResourceResult
    {
        return ProfileResourceResult::failure(
            'not_found',
            ['resource_not_found', 'id' => $id],
            $metadata
        );
    }

    protected function resultMetadata(RuntimeContext $context, string $action): array
    {
        $request = $context->metadata();

        return [
            'resource' => $this->resource->val(),
            'action' => $action,
            'scope' => $this->scope->toArray(),
            'actor' => Arr::is($request['actor'] ?? null) ? $request['actor'] : [],
            'correlation_id' => $request['correlation_id'] ?? null,
            'session_id' => $request['session_id'] ?? null,
        ];
    }

    private function scopeAllowed(RuntimeContext $context): bool
    {
        $metadata = $context->metadata();
        try {
            $caller = ProfileScope::fromContext($metadata);
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($this->scope->matches($caller)) {
            return true;
        }
        if (!$context->hasCapability('wise.profile.cross_scope')) {
            return false;
        }

        $grants = Arr::is($metadata['profile_scope_grants'] ?? null)
            ? $metadata['profile_scope_grants']
            : [];

        return Arr::has($grants, $this->scope->key(), true);
    }

    private function normalizeId(?string $id): string
    {
        return Val::is($id) ? Str::make((string)$id)->trim()->lower()->val() : '';
    }
}
