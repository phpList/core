<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Client;

use PhpList\Core\Domain\Search\Exception\SearchBackendUnavailableException;

/**
 * Thin, mockable contract over the vendor Elastic\Elasticsearch\Client (which is `final` and exposes
 * dozens of generated endpoint methods). Every method wraps vendor exceptions in
 * SearchBackendUnavailableException, so nothing outside ElasticsearchClientAdapter depends on the
 * vendor's exception hierarchy.
 */
interface ElasticsearchClientInterface
{
    /**
     * Returns quietly (idempotent) if $revision is older than the revision currently stored for this
     * document - a delayed retry of a stale write must never resurrect/overwrite newer state.
     * @param array<string, mixed> $document
     * @throws SearchBackendUnavailableException
     */
    public function index(string $indexName, string $documentId, array $document, int $revision): void;

    /**
     * Returns quietly (idempotent) if the document does not exist, or if $revision is older than the
     * revision currently stored for this document.
     * @throws SearchBackendUnavailableException
     */
    public function delete(string $indexName, string $documentId, int $revision): void;

    /** @throws SearchBackendUnavailableException */
    public function indexExists(string $indexName): bool;

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, mixed> $settings
     * @throws SearchBackendUnavailableException
     */
    public function createIndex(string $indexName, array $mapping, array $settings): void;

    /**
     * @param array<string, mixed> $mapping
     * @throws SearchBackendUnavailableException
     */
    public function updateMapping(string $indexName, array $mapping): void;

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed> Raw decoded ES response body.
     * @throws SearchBackendUnavailableException
     */
    public function search(string $indexName, array $query): array;
}
