<?php

namespace BlueFission\Tests;

use BlueFission\Automata\Context;
use BlueFission\Wise\Sys\Memory\WorkingMemoryCoordinator;
use BlueFission\Wise\Sys\Memory\DefaultMemoryPolicy;
use BlueFission\Wise\Usr\Profile;
use PHPUnit\Framework\TestCase;

final class WorkingMemoryCoordinatorTest extends TestCase
{
    public function testUserScopedRecordRequiresMatchingProfile(): void
    {
        $reader = new TestMemoryReader();
        $coordinator = new WorkingMemoryCoordinator($reader, new DefaultMemoryPolicy());

        $actor = new Profile('user-1', ['user']);
        $result = $coordinator->record('user', 'hello', 'episode-1', $actor, 'user-2');

        $this->assertFalse($result);
    }

    public function testUserScopedRecordStoresMemory(): void
    {
        $reader = new TestMemoryReader();
        $coordinator = new WorkingMemoryCoordinator($reader, new DefaultMemoryPolicy());

        $actor = new Profile('user-1', ['user']);
        $result = $coordinator->record('user', 'hello', 'episode-1', $actor, 'user-1');

        $this->assertTrue($result);
        $partition = $coordinator->memory('user', $actor, 'user-1');
        $this->assertNotNull($partition);
        $this->assertNotEmpty($partition->memory()->contents());
    }

    public function testGlobalRecordRequiresAdmin(): void
    {
        $reader = new TestMemoryReader();
        $coordinator = new WorkingMemoryCoordinator($reader, new DefaultMemoryPolicy());

        $actor = new Profile('user-1', ['user']);
        $result = $coordinator->record('global', 'hello', 'episode-1', $actor);

        $this->assertFalse($result);

        $admin = new Profile('admin-1', ['admin']);
        $result = $coordinator->record('global', 'hello', 'episode-2', $admin);
        $this->assertTrue($result);
    }

    public function testMaxSizeTrimsOldEntries(): void
    {
        $reader = new TestMemoryReader();
        $coordinator = new WorkingMemoryCoordinator($reader, new DefaultMemoryPolicy());
        $coordinator->setUserMaxSize(1);

        $actor = new Profile('user-1', ['user']);
        $coordinator->record('user', 'first', 'episode-1', $actor, 'user-1');
        $coordinator->record('user', 'second', 'episode-2', $actor, 'user-1');

        $partition = $coordinator->memory('user', $actor, 'user-1');
        $this->assertNotNull($partition);
        $this->assertLessThanOrEqual(1, count($partition->memory()->contents()));
    }

    public function testPartitionMaxSizeReturnsConfiguredLimits(): void
    {
        $reader = new TestMemoryReader();
        $coordinator = new WorkingMemoryCoordinator($reader, new DefaultMemoryPolicy());

        $coordinator->setGlobalMaxSize(5);
        $coordinator->setUserMaxSize(3);

        $this->assertSame(5, $coordinator->partitionMaxSize('global'));
        $this->assertSame(3, $coordinator->partitionMaxSize('user', 'user-1'));
    }
}

final class TestMemoryReader
{
    public function readDocument(string $text): array
    {
        return [$text];
    }

    public function toHoloscene(array $statements, $holoscene, $memory, string $episodeId): void
    {
        $context = new Context();
        $context->set('label', 'input');
        $context->set('value', $statements[0] ?? '');
        $memory->addMemory($episodeId, $context);
        $holoscene->push($episodeId, $statements);
    }
}
