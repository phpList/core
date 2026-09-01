<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Search\Service;

use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;
use PhpList\Core\Domain\Search\Service\ElasticsearchIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ElasticsearchIndexerTest extends TestCase
{
    private ElasticsearchClientInterface&MockObject $client;
    private ElasticsearchIndexer $indexer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->indexer = new ElasticsearchIndexer($this->client, 'phplist_');
    }

    public function testIndexAppliesIndexPrefix(): void
    {
        $this->client
            ->expects($this->once())
            ->method('index')
            ->with('phplist_subscriber_history', '42', ['id' => 42]);

        $this->indexer->index('subscriber_history', '42', ['id' => 42]);
    }

    public function testDeleteAppliesIndexPrefix(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with('phplist_subscriber_history', '42');

        $this->indexer->delete('subscriber_history', '42');
    }

    public function testCreateOrUpdateIndexCreatesWhenAbsent(): void
    {
        $definition = $this->createMock(SearchIndexDefinitionInterface::class);
        $definition->method('getIndexAlias')->willReturn('subscriber_history');
        $definition->method('getMapping')->willReturn(['properties' => ['id' => ['type' => 'keyword']]]);
        $definition->method('getSettings')->willReturn([]);

        $this->client
            ->expects($this->once())
            ->method('indexExists')
            ->with('phplist_subscriber_history')
            ->willReturn(false);

        $this->client
            ->expects($this->once())
            ->method('createIndex')
            ->with('phplist_subscriber_history', $definition->getMapping(), []);
        $this->client->expects($this->never())->method('updateMapping');

        $this->indexer->createOrUpdateIndex($definition);
    }

    public function testCreateOrUpdateIndexUpdatesMappingWhenPresent(): void
    {
        $definition = $this->createMock(SearchIndexDefinitionInterface::class);
        $definition->method('getIndexAlias')->willReturn('subscriber_history');
        $definition->method('getMapping')->willReturn(['properties' => ['id' => ['type' => 'keyword']]]);
        $definition->method('getSettings')->willReturn([]);

        $this->client
            ->expects($this->once())
            ->method('indexExists')
            ->with('phplist_subscriber_history')
            ->willReturn(true);

        $this->client
            ->expects($this->once())
            ->method('updateMapping')
            ->with('phplist_subscriber_history', $definition->getMapping());
        $this->client->expects($this->never())->method('createIndex');

        $this->indexer->createOrUpdateIndex($definition);
    }
}
