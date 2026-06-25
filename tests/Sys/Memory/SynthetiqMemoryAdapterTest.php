<?php

namespace BlueFission\Tests\Sys\Memory;

use BlueFission\Automata\Context;
use BlueFission\Wise\Sys\Memory\SynthetiqMemoryAdapter;
use BlueFission\Wise\Sys\Memory\WorkingMemoryCoordinator;
use BlueFission\Wise\Usr\Profile;
use PHPUnit\Framework\TestCase;

final class SynthetiqMemoryAdapterTest extends TestCase
{
    public function testRecordExchangeStoresMemoryAndRecalls(): void
    {
        $reader = new class() {
            public function readDocument(string $text): array
            {
                return [$text];
            }

            public function toHoloscene(array $statements, $holoscene, $memory, string $episodeId): void
            {
                foreach ($statements as $statement) {
                    $context = new Context();
                    $context->set('input', $statement);
                    $context->set('episode_id', $episodeId);
                    $memory->addMemory($episodeId . ':' . md5($statement), $context);
                }
            }
        };

        $coordinator = new WorkingMemoryCoordinator($reader);
        $adapter = new SynthetiqMemoryAdapter(
            $coordinator,
            new Profile('system', ['system']),
            ['default_scope' => 'user', 'similarity_threshold' => 0.0]
        );

        $context = new Context();
        $context->set('user_id', 'alice');

        $adapter->recordExchange('hello', 'world', $context, [
            'scope' => 'user',
            'user_id' => 'alice',
            'episode_id' => 'episode-1',
        ]);

        $recall = $adapter->recall('hello', $context, [
            'scope' => 'user',
            'user_id' => 'alice',
        ]);

        $this->assertFalse($recall->isEmpty());
        $this->assertSame('user', $recall->meta()['scope'] ?? null);
        $this->assertSame('alice', $recall->meta()['owner_id'] ?? null);
    }
}
