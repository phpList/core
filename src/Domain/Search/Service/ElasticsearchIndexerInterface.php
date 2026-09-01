<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Service;

use PhpList\Core\Domain\Search\Exception\SearchBackendUnavailableException;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;

interface ElasticsearchIndexerInterface
{
    /**
     * @param array<string, mixed> $document
     * @throws SearchBackendUnavailableException
     */
    public function index(string $indexAlias, string $documentId, array $document): void;

    /** @throws SearchBackendUnavailableException */
    public function delete(string $indexAlias, string $documentId): void;

    /**
     * Creates the index with its mapping/settings if absent, otherwise applies the mapping
     * non-destructively (never drops/recreates an existing index).
     * @throws SearchBackendUnavailableException
     */
    public function createOrUpdateIndex(SearchIndexDefinitionInterface $definition): void;
}
