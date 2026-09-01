<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use PhpList\Core\Domain\Search\Exception\SearchBackendUnavailableException;
use Throwable;

class ElasticsearchClientAdapter implements ElasticsearchClientInterface
{
    private const HTTP_NOT_FOUND = 404;

    public function __construct(private readonly Client $client)
    {
    }

    public function index(string $indexName, string $documentId, array $document): void
    {
        $this->call(function () use ($indexName, $documentId, $document): void {
            $this->client->index([
                'index' => $indexName,
                'id' => $documentId,
                'body' => $document,
            ]);
        });
    }

    public function delete(string $indexName, string $documentId): void
    {
        $this->call(function () use ($indexName, $documentId): void {
            try {
                $this->client->delete([
                    'index' => $indexName,
                    'id' => $documentId,
                ]);
            } catch (ClientResponseException $exception) {
                if ($exception->getCode() !== self::HTTP_NOT_FOUND) {
                    throw $exception;
                }
            }
        });
    }

    public function indexExists(string $indexName): bool
    {
        return $this->call(fn (): bool => $this->client->indices()->exists(['index' => $indexName])->asBool());
    }

    public function createIndex(string $indexName, array $mapping, array $settings): void
    {
        $this->call(function () use ($indexName, $mapping, $settings): void {
            $body = ['mappings' => $mapping];
            if ($settings !== []) {
                $body['settings'] = $settings;
            }

            $this->client->indices()->create([
                'index' => $indexName,
                'body' => $body,
            ]);
        });
    }

    public function updateMapping(string $indexName, array $mapping): void
    {
        $this->call(function () use ($indexName, $mapping): void {
            $this->client->indices()->putMapping([
                'index' => $indexName,
                'body' => $mapping,
            ]);
        });
    }

    public function search(string $indexName, array $query): array
    {
        return $this->call(fn (): array => $this->client->search([
            'index' => $indexName,
            'body' => $query,
        ])->asArray());
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws SearchBackendUnavailableException
     */
    private function call(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            throw new SearchBackendUnavailableException(
                'Elasticsearch operation failed: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }
}
