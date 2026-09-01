<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Service;

use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;

class ElasticsearchIndexer implements ElasticsearchIndexerInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly string $indexPrefix,
    ) {
    }

    public function index(string $indexAlias, string $documentId, array $document): void
    {
        $this->client->index($this->resolvePhysicalIndexName($indexAlias), $documentId, $document);
    }

    public function delete(string $indexAlias, string $documentId): void
    {
        $this->client->delete($this->resolvePhysicalIndexName($indexAlias), $documentId);
    }

    public function createOrUpdateIndex(SearchIndexDefinitionInterface $definition): void
    {
        $indexName = $this->resolvePhysicalIndexName($definition->getIndexAlias());

        if ($this->client->indexExists($indexName)) {
            $this->client->updateMapping($indexName, $definition->getMapping());

            return;
        }

        $this->client->createIndex($indexName, $definition->getMapping(), $definition->getSettings());
    }

    private function resolvePhysicalIndexName(string $indexAlias): string
    {
        return $this->indexPrefix . $indexAlias;
    }
}
