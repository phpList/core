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
     * @param array<string, mixed> $document
     * @throws SearchBackendUnavailableException
     */
    public function index(string $indexName, string $documentId, array $document): void;

    /**
     * Returns quietly (idempotent) if the document does not exist.
     * @throws SearchBackendUnavailableException
     */
    public function delete(string $indexName, string $documentId): void;

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
