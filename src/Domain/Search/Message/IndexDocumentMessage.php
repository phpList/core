<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Message;

use PhpList\Core\Domain\Search\Model\SearchOperation;

/**
 * Carries a fully rendered document snapshot rather than an entity reference, so the handler never
 * needs to re-query the database (which would race against replication/visibility and would defeat
 * the point of an async dual-write).
 */
class IndexDocumentMessage
{
    /** @param array<string, mixed> $document */
    public function __construct(
        private readonly string $indexName,
        private readonly string $documentId,
        private readonly array $document,
        private readonly SearchOperation $operation,
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /** @return array<string, mixed> */
    public function getDocument(): array
    {
        return $this->document;
    }

    public function getOperation(): SearchOperation
    {
        return $this->operation;
    }
}
