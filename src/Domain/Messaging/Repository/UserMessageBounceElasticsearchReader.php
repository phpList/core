<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Messaging\Model\Filter\UserMessageBounceFilter;
use PhpList\Core\Domain\Messaging\Model\Interfaces\UserMessageBounceRecordInterface;
use PhpList\Core\Domain\Messaging\Model\ReadModel\UserMessageBounceReadModel;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\Interfaces\UserMessageBounceReaderInterface;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;

/**
 * ES-backed counterpart of UserMessageBounceRepository. Read-only - dual-write is handled entirely
 * by SearchIndexDoctrineListener, not by this class. Any Elasticsearch failure surfaces as
 * SearchBackendUnavailableException (via ElasticsearchClientInterface) with no fallback to the database.
 */
class UserMessageBounceElasticsearchReader implements UserMessageBounceReaderInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly string $indexPrefix,
    ) {
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        if (!$filter instanceof UserMessageBounceFilter) {
            throw new InvalidArgumentException('Expected UserMessageBounceFilter.');
        }

        $mustFilters = [];

        if ($filter->getUserId() !== null) {
            $mustFilters[] = ['term' => ['userId' => $filter->getUserId()]];
        }

        if ($filter->getMessageId() !== null) {
            $mustFilters[] = ['term' => ['messageId' => $filter->getMessageId()]];
        }

        if ($filter->getBounceId() !== null) {
            $mustFilters[] = ['term' => ['bounceId' => $filter->getBounceId()]];
        }

        if ($filter->getDateFrom() !== null) {
            $mustFilters[] = ['range' => ['time' => ['gte' => $filter->getDateFrom()->format(DATE_ATOM)]]];
        }

        $mustFilters[] = ['range' => ['idSort' => ['gt' => $filter->getLastId()]]];

        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['bool' => ['filter' => $mustFilters]],
                'sort' => [['idSort' => 'asc']],
                'size' => $filter->getLimit(),
                'track_total_hits' => true,
            ],
        );

        $hits = $response['hits']['hits'] ?? [];
        $lastHit = $hits !== [] ? $hits[array_key_last($hits)] : null;

        return new PaginatedResult(
            items: array_map($this->hydrate(...), $hits),
            total: (int) ($response['hits']['total']['value'] ?? 0),
            limit: $filter->getLimit(),
            lastId: $lastHit !== null ? (int) $lastHit['_source']['idSort'] : $filter->getLastId(),
        );
    }

    /** @return UserMessageBounceRecordInterface[] */
    public function getByUserId(int $userId): array
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['term' => ['userId' => $userId]],
                'sort' => [['idSort' => 'desc']],
                // 10000 is enough, I think, but if we ever need more, we can implement pagination here too.
                'size' => 10000,
            ],
        );

        $hits = $response['hits']['hits'] ?? [];

        return array_map($this->hydrate(...), $hits);
    }

    public function getCountByMessageId(int $messageId): int
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['term' => ['messageId' => $messageId]],
                'size' => 0,
                'track_total_hits' => true,
            ],
        );

        return (int) ($response['hits']['total']['value'] ?? 0);
    }

    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['range' => ['time' => [
                    'gte' => $start->format(DateTimeInterface::ATOM),
                    'lte' => $end->format(DateTimeInterface::ATOM),
                ]]],
                'size' => 0,
                'track_total_hits' => true,
            ],
        );

        return (int) ($response['hits']['total']['value'] ?? 0);
    }

    public function existsByMessageIdAndUserId(int $messageId, int $subscriberId): bool
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['bool' => ['filter' => [
                    ['term' => ['messageId' => $messageId]],
                    ['term' => ['userId' => $subscriberId]],
                ]]],
                'size' => 0,
                'track_total_hits' => true,
            ],
        );

        return ((int) ($response['hits']['total']['value'] ?? 0)) > 0;
    }

    /** @param array{_source: array<string, mixed>} $hit */
    private function hydrate(array $hit): UserMessageBounceReadModel
    {
        $source = $hit['_source'];

        return new UserMessageBounceReadModel(
            id: isset($source['id']) ? (int) $source['id'] : null,
            userId: (int) $source['userId'],
            messageId: (int) $source['messageId'],
            bounceId: (int) $source['bounceId'],
            createdAt: isset($source['time'])
                ? (DateTime::createFromFormat(DATE_ATOM, $source['time']) ?: new DateTime())
                : new DateTime(),
        );
    }

    private function resolvePhysicalIndexName(): string
    {
        return $this->indexPrefix . UserMessageBounce::SEARCH_INDEX_NAME;
    }
}
