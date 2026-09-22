<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Search\Fake;

use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;

/**
 * Mirrors real Elasticsearch's `version_type: external_gte` semantics well enough to test
 * revision-based conflict rejection without a live cluster: index()/delete() are no-ops whenever
 * $revision is older than the revision last accepted for that document. The real vendor `Client` is
 * `final`, so this is the only way to unit-test that ordering guarantee.
 */
class InMemoryVersionedElasticsearchClient implements ElasticsearchClientInterface
{
    /** @var array<string, int> */
    private array $revisions = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $documents = [];

    public function index(string $indexName, string $documentId, array $document, int $revision): void
    {
        $key = $indexName . '|' . $documentId;
        if (isset($this->revisions[$key]) && $revision < $this->revisions[$key]) {
            return;
        }

        $this->revisions[$key] = $revision;
        $this->documents[$key] = $document;
    }

    public function delete(string $indexName, string $documentId, int $revision): void
    {
        $key = $indexName . '|' . $documentId;
        if (isset($this->revisions[$key]) && $revision < $this->revisions[$key]) {
            return;
        }

        $this->revisions[$key] = $revision;
        $this->documents[$key] = null;
    }

    /** @return array<string, mixed>|null */
    public function getDocument(string $indexName, string $documentId): ?array
    {
        return $this->documents[$indexName . '|' . $documentId] ?? null;
    }

    public function indexExists(string $indexName): bool
    {
        return true;
    }

    public function createIndex(string $indexName, array $mapping, array $settings): void
    {
    }

    public function updateMapping(string $indexName, array $mapping): void
    {
    }

    public function search(string $indexName, array $query): array
    {
        return [];
    }
}
