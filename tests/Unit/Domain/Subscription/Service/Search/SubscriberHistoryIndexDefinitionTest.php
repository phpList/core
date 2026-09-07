<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Search;

use PhpList\Core\Domain\Subscription\Service\Search\SubscriberHistoryIndexDefinition;
use PHPUnit\Framework\TestCase;

class SubscriberHistoryIndexDefinitionTest extends TestCase
{
    public function testAliasMatchesEntitySearchIndexName(): void
    {
        $definition = new SubscriberHistoryIndexDefinition();

        $this->assertSame('subscriber_history', $definition->getIndexAlias());
    }

    public function testMappingDeclaresExpectedFields(): void
    {
        $definition = new SubscriberHistoryIndexDefinition();
        $properties = $definition->getMapping()['properties'];

        foreach (['id', 'idSort', 'subscriberId', 'ip', 'date', 'summary', 'detail', 'systemInfo'] as $field) {
            $this->assertArrayHasKey($field, $properties);
        }
        $this->assertSame('long', $properties['idSort']['type']);
        $this->assertSame('keyword', $properties['id']['type']);
    }

    public function testSettingsAreEmptyByDefault(): void
    {
        $definition = new SubscriberHistoryIndexDefinition();

        $this->assertSame([], $definition->getSettings());
    }
}
