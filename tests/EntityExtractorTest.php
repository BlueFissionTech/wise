<?php

namespace BlueFission\Tests;

use BlueFission\Automata\Language\EntityExtractor;
use PHPUnit\Framework\TestCase;

final class EntityExtractorTest extends TestCase
{
    public function testExtractsCommonEntities(): void
    {
        $extractor = new EntityExtractor();

        $input = 'Email me at test@example.com on 2024-07-01 at 9:30am. Visit https://example.com #tag @user $value 0xFF "literal"';

        $this->assertSame(['2024-07-01'], $extractor->date($input));
        $this->assertSame(['9:30am'], $extractor->time($input));
        $this->assertSame(['https://example.com'], $extractor->web($input));
        $this->assertSame(['test@example.com'], $extractor->email($input));
        $this->assertSame(['#tag'], $extractor->tags($input));
        $this->assertSame(['@example', '@user'], $extractor->mentions($input));
        $this->assertSame(['$value'], $extractor->values($input));
        $this->assertSame(['0xFF'], $extractor->hex($input));
        $this->assertSame(['"literal"'], $extractor->literals($input));
    }

    public function testExtractsOperationsAndNumbers(): void
    {
        $extractor = new EntityExtractor();

        $input = 'calc 3 + 5 and ten - two';

        $this->assertSame(['3 + 5', 'ten - two'], $extractor->operation($input));
        $this->assertSame(['3', '5', 'ten', 'two'], $extractor->number($input));
    }
}
