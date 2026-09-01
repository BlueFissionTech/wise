<?php

namespace BlueFission\Tests\Res;

use BlueFission\Data\Storage\Memory;
use BlueFission\Wise\Cmd\RuntimeContext;
use BlueFission\Wise\Res\FunctionResource;
use BlueFission\Wise\Res\GoalResource;
use BlueFission\Wise\Res\ProfileScope;
use BlueFission\Wise\Res\ProfileStepResource;
use BlueFission\Wise\Res\ResourceCatalog;
use BlueFission\Wise\Res\StepResource;
use BlueFission\Wise\Res\StorageProfileResourceStore;
use PHPUnit\Framework\TestCase;

final class ProfileResourceTest extends TestCase
{
    private const ALL_CAPABILITIES = [
        'wise.profile.function.read',
        'wise.profile.function.write',
        'wise.profile.function.invoke',
        'wise.profile.goal.read',
        'wise.profile.goal.write',
        'wise.profile.step.read',
        'wise.profile.step.write',
    ];

    public function testFunctionLifecycleIsStructuredAndHostNeutral(): void
    {
        $scope = $this->scope('profile-a');
        $storage = new Memory();
        $resource = new FunctionResource(
            new StorageProfileResourceStore($storage),
            $scope,
            static fn (array $definition, array $arguments): string =>
                $definition['handler'] . ':' . ($arguments['name'] ?? 'unknown')
        );
        $context = $this->context($scope, self::ALL_CAPABILITIES);

        $created = $resource->execute('create', 'welcome', [
            'handler' => 'profile.welcome',
            'description' => 'Welcome a named participant.',
            'parameters' => ['name' => ['type' => 'string']],
        ], $context);
        $invoked = $resource->execute(
            'invoke',
            'welcome',
            ['arguments' => ['name' => 'Ada']],
            $context
        );

        $this->assertTrue($created->successful());
        $this->assertSame('invoked', $invoked->status());
        $this->assertSame('profile.welcome:Ada', $invoked->data()['result']);
        $this->assertSame('corr-a', $invoked->metadata()['correlation_id']);
        $this->assertSame('session-a', $invoked->metadata()['session_id']);
        $this->assertStringNotContainsString('corr-a', serialize($storage->profileResources));
        $this->assertStringNotContainsString('session-a', serialize($storage->profileResources));
    }

    public function testDescriptorsAreCapabilityFilteredBeforeDiscovery(): void
    {
        $scope = $this->scope('profile-a');
        $resource = new FunctionResource(
            new StorageProfileResourceStore(new Memory()),
            $scope
        );
        $context = $this->context($scope, ['wise.profile.function.read']);

        $identifiers = array_column($resource->descriptors($context), 'identifier');

        $this->assertSame([
            'resource:function.get',
            'resource:function.list',
        ], $identifiers);
        $this->assertFalse($resource->execute('create', 'hidden', [
            'handler' => 'hidden.handler',
        ], $context)->successful());
    }

    public function testProfilesRemainIsolatedWithoutBothCrossScopeConditions(): void
    {
        $scopeA = $this->scope('profile-a');
        $scopeB = $this->scope('profile-b');
        $store = new StorageProfileResourceStore(new Memory());
        $resourceA = new GoalResource($store, $scopeA);
        $resourceB = new GoalResource($store, $scopeB);

        $resourceA->execute('create', 'ship', ['title' => 'Ship'], $this->context($scopeA, self::ALL_CAPABILITIES));

        $this->assertSame([], $resourceB->execute(
            'list',
            null,
            [],
            $this->context($scopeB, self::ALL_CAPABILITIES)
        )->data());
        $this->assertSame([], $resourceA->descriptors($this->context(
            $scopeB,
            self::ALL_CAPABILITIES
        )));
        $this->assertFalse($resourceA->execute(
            'get',
            'ship',
            [],
            $this->context($scopeB, [...self::ALL_CAPABILITIES, 'wise.profile.cross_scope'])
        )->successful());

        $granted = $this->context(
            $scopeB,
            [...self::ALL_CAPABILITIES, 'wise.profile.cross_scope'],
            [$scopeA->key()]
        );
        $this->assertSame('ship', $resourceA->execute('get', 'ship', [], $granted)->data()['id']);
    }

    public function testGoalsAndStepsUseSeparateRecordsAndLegacyStepRemainsAvailable(): void
    {
        $scope = $this->scope('profile-a');
        $store = new StorageProfileResourceStore(new Memory());
        $context = $this->context($scope, self::ALL_CAPABILITIES);
        $goals = new GoalResource($store, $scope);
        $steps = new ProfileStepResource($store, $scope);

        $goals->execute('create', 'release', ['title' => 'Release'], $context);
        $created = $steps->execute('create', 'verify', [
            'goal_id' => 'release',
            'description' => 'Verify the release.',
            'position' => 1,
        ], $context);
        $transitioned = $goals->execute('transition', 'release', ['status' => 'active'], $context);

        $this->assertTrue($created->successful());
        $this->assertSame('release', $created->data()['goal_id']);
        $this->assertSame('active', $transitioned->data()['status']);
        $this->assertSame('pending', $steps->execute('get', 'verify', [], $context)->data()['status']);

        $catalog = new ResourceCatalog();
        $this->assertSame(GoalResource::class, $catalog->classFor('goal'));
        $this->assertSame(FunctionResource::class, $catalog->classFor('functions'));
        $this->assertSame(ProfileStepResource::class, $catalog->classFor('profile steps'));
        $this->assertSame(StepResource::class, $catalog->classFor('step'));
    }

    private function scope(string $profileId): ProfileScope
    {
        return new ProfileScope('tenant-a', 'application-a', $profileId, $profileId, 'central_agent');
    }

    private function context(ProfileScope $scope, array $capabilities, array $grants = []): RuntimeContext
    {
        $scopeData = $scope->toArray();

        return new RuntimeContext(
            metadata: [
                ...$scopeData,
                'profile_scope_grants' => $grants,
                'correlation_id' => 'corr-a',
                'session_id' => 'session-a',
            ],
            actor: [
                'id' => $scopeData['owner_id'],
                'profile_id' => $scopeData['profile_id'],
                'owner_type' => $scopeData['owner_type'],
            ],
            capabilities: $capabilities
        );
    }
}
