<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Search;

use PhpList\Core\Domain\Messaging\Service\Search\UserMessageBounceIndexDefinition;
use PHPUnit\Framework\TestCase;

class UserMessageBounceIndexDefinitionTest extends TestCase
{
    public function testAliasMatchesEntitySearchIndexName(): void
    {
        $definition = new UserMessageBounceIndexDefinition();

        $this->assertSame('user_message_bounce', $definition->getIndexAlias());
    }

    public function testMappingDeclaresExpectedFields(): void
    {
        $definition = new UserMessageBounceIndexDefinition();
        $properties = $definition->getMapping()['properties'];

        foreach (['id', 'idSort', 'userId', 'messageId', 'bounceId', 'time'] as $field) {
            $this->assertArrayHasKey($field, $properties);
        }
        $this->assertSame('long', $properties['idSort']['type']);
        $this->assertSame('keyword', $properties['id']['type']);
    }

    public function testSettingsAreEmptyByDefault(): void
    {
        $definition = new UserMessageBounceIndexDefinition();

        $this->assertSame([], $definition->getSettings());
    }
}
