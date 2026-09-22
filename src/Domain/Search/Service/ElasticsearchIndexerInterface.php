<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Service;

use PhpList\Core\Domain\Search\Exception\SearchBackendUnavailableException;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;

interface ElasticsearchIndexerInterface
{
    /**
     * @param array<string, mixed> $document
     * @param int $revision Monotonic per-document revision; writes older than the last applied
     *     revision for this document are dropped instead of applied (see ElasticsearchClientAdapter).
     * @throws SearchBackendUnavailableException
     */
    public function index(string $indexAlias, string $documentId, array $document, int $revision): void;

    /**
     * @param int $revision Monotonic per-document revision; see index().
     * @throws SearchBackendUnavailableException
     */
    public function delete(string $indexAlias, string $documentId, int $revision): void;

    /**
     * Creates the index with its mapping/settings if absent, otherwise applies the mapping
     * non-destructively (never drops/recreates an existing index).
     * @throws SearchBackendUnavailableException
     */
    public function createOrUpdateIndex(SearchIndexDefinitionInterface $definition): void;
}
