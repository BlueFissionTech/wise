<?php

namespace BlueFission\Tests\Sys\Memory;

use BlueFission\Automata\Context;
use BlueFission\Wise\Sys\Memory\MemoryPartition;
use PHPUnit\Framework\TestCase;

final class MemoryPartitionTest extends TestCase
{
    public function testRetentionPrunesOldestEntriesWhenMaxSizeExceeded(): void
    {
        $reader = new class() {
            public function readDocument(string $text): array
            {
                return [$text];
            }

            public function toHoloscene(array $statements, $holoscene, $memory, string $episodeId): void
            {
            }
        };

        $partition = new MemoryPartition($reader);
        $partition->setMaxSize(2);

        $now = time();

        $oldest = new Context();
        $oldest->set('last_seen', $now - 7200);
        $partition->recordContext($oldest, 'entry-1');

        $middle = new Context();
        $middle->set('last_seen', $now - 3600);
        $partition->recordContext($middle, 'entry-2');

        $newest = new Context();
        $newest->set('last_seen', $now - 10);
        $partition->recordContext($newest, 'entry-3');

        $contents = $partition->memory()->contents();

        $this->assertCount(2, $contents);
        $this->assertArrayNotHasKey('entry-1', $contents);
        $this->assertArrayHasKey('entry-2', $contents);
        $this->assertArrayHasKey('entry-3', $contents);
    }
}
