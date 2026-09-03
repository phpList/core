<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use DateTime;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\Interfaces\SubscriberHistoryRecordInterface;
use PhpList\Core\Domain\Subscription\Model\ReadModel\SubscriberHistoryReadModel;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\Interfaces\SubscriberHistoryReaderInterface;

/**
 * ES-backed counterpart of SubscriberHistoryRepository. Read-only - dual-write is handled entirely
 * by SearchIndexDoctrineListener, not by this class. Any Elasticsearch failure surfaces as
 * SearchBackendUnavailableException (via ElasticsearchClientInterface) with no fallback to the database.
 */
class SubscriberHistoryElasticsearchReader implements SubscriberHistoryReaderInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly string $indexPrefix,
    ) {
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        if (!$filter instanceof SubscriberHistoryFilter) {
            throw new InvalidArgumentException('Expected SubscriberHistoryFilter.');
        }

        $mustFilters = [];

        if ($filter->getSubscriber() !== null) {
            $mustFilters[] = ['term' => ['subscriberId' => $filter->getSubscriber()->getId()]];
        }

        if ($filter->getDateFrom() !== null) {
            $mustFilters[] = ['range' => ['date' => ['gte' => $filter->getDateFrom()->format(DATE_ATOM)]]];
        }

        if ($filter->getIp() !== null) {
            $mustFilters[] = ['term' => ['ip' => $filter->getIp()]];
        }

        if ($filter->getSummery() !== null) {
            $mustFilters[] = ['term' => ['summary.keyword' => $filter->getSummery()]];
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

    /** @return SubscriberHistoryRecordInterface[] */
    public function getBySubscriber(Subscriber $subscriber): array
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['term' => ['subscriberId' => $subscriber->getId()]],
                'sort' => [['idSort' => 'desc']],
                'size' => SubscriberHistory::MAX_RESULTS_BY_USER,
            ],
        );

        $hits = $response['hits']['hits'] ?? [];

        return array_map($this->hydrate(...), $hits);
    }

    /** @param array{_source: array<string, mixed>} $hit */
    private function hydrate(array $hit): SubscriberHistoryReadModel
    {
        $source = $hit['_source'];

        return new SubscriberHistoryReadModel(
            id: isset($source['id']) ? (int) $source['id'] : null,
            subscriberId: isset($source['subscriberId']) ? (int) $source['subscriberId'] : null,
            ip: $source['ip'] ?? null,
            createdAt: isset($source['date']) ? DateTime::createFromFormat(DATE_ATOM, $source['date']) ?: null : null,
            summary: $source['summary'] ?? null,
            detail: $source['detail'] ?? null,
            systemInfo: $source['systemInfo'] ?? null,
        );
    }

    private function resolvePhysicalIndexName(): string
    {
        return $this->indexPrefix . SubscriberHistory::SEARCH_INDEX_NAME;
    }
}
