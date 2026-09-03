<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Model\Interfaces;

/**
 * Implemented by any entity that should be dual-written to Elasticsearch on persist/update/remove.
 * Picked up automatically by SearchIndexDoctrineListener - no per-entity wiring beyond this interface
 * and a matching SearchIndexDefinitionInterface/SearchReindexProviderInterface.
 */
interface SearchIndexableInterface
{
    public function getSearchIndexName(): string;

    public function getSearchDocumentId(): string;

    /** @return array<string, mixed> */
    public function toSearchDocument(): array;
}
