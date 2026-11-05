<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTime;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\Interfaces\UserMessageBounceRecordInterface;
use PhpList\Core\Domain\Messaging\Model\ReadModel\UserMessageBounceReadModel;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\Interfaces\UserMessageBounceReportReaderInterface;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

/**
 * Reporting/joining queries that correlate Elasticsearch-backed bounce data with related entities
 * that live in MySQL only (Subscriber/Subscription, Message, Bounce, UserMessage). These aren't
 * "the ES implementation of UserMessageBounceReaderInterface" - a plain ES reader has no business
 * running Doctrine joins - they're a distinct concern: legacy report/processing queries that used to
 * join straight onto the (now potentially huge) user_message_bounce table in one SQL statement, and
 * now get the bounce side of that join from Elasticsearch instead, merging in PHP.
 *
 * Only makes sense when bounce reads are Elasticsearch-backed - UserMessageBounceRepository already
 * has the plain single-query SQL join versions of all four methods for when they aren't.
 * getListBounceTotals/getCampaignBounceTotals are also declared on UserMessageBounceReportReaderInterface,
 * which is what external consumers (e.g. phplist/rest-api) should depend on instead of this concrete
 * class - see config/services/repositories.yml for the DI alias.
 */
class UserMessageBounceElasticsearchHybridReader implements UserMessageBounceReportReaderInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly string $indexPrefix,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageRepository $messageRepository,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly BounceRepository $bounceRepository,
    ) {
    }

    /**
     * @return array<int, array{
     *   subscriber_id: int,
     *   email: string,
     *   confirmed: bool,
     *   blacklisted: bool,
     *   total_bounces: int
     * }>
     */
    public function getListBounceTotals(int $listId): array
    {
        $rows = $this->subscriberRepository->getBounceReportRowsByListId($listId);

        if ($rows === []) {
            return [];
        }

        $subscriberIds = array_map(static fn (array $row): int => (int) $row['subscriberId'], $rows);
        $totalsByUserId = $this->countsByTermsField('userId', $subscriberIds);

        $result = [];
        foreach ($rows as $row) {
            $subscriberId = (int) $row['subscriberId'];
            $totalBounces = $totalsByUserId[$subscriberId] ?? 0;

            if ($totalBounces === 0) {
                continue;
            }

            $result[] = [
                'subscriber_id' => $subscriberId,
                'email' => (string) $row['email'],
                'confirmed' => (bool) $row['confirmed'],
                'blacklisted' => (bool) $row['blacklisted'],
                'total_bounces' => $totalBounces,
            ];
        }

        return $result;
    }

    /** @return array<int, array{message_id: int, subject: string, total_bounces: int}> */
    public function getCampaignBounceTotals(?int $ownerId = null): array
    {
        $rows = $this->messageRepository->getSubjectsForBounceReport($ownerId);

        if ($rows === []) {
            return [];
        }

        $messageIds = array_map(static fn (array $row): int => (int) $row['messageId'], $rows);
        $totalsByMessageId = $this->countsByTermsField('messageId', $messageIds);

        $result = [];
        foreach ($rows as $row) {
            $messageId = (int) $row['messageId'];
            $totalBounces = $totalsByMessageId[$messageId] ?? 0;

            if ($totalBounces === 0) {
                continue;
            }

            $result[] = [
                'message_id' => $messageId,
                'subject' => $row['subject'],
                'total_bounces' => $totalBounces,
            ];
        }

        return $result;
    }

    /** @return array<int, array{umb: UserMessageBounceRecordInterface, bounce: Bounce}> */
    public function getPaginatedWithJoinNoRelation(int $fromId, int $limit): array
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['range' => ['idSort' => ['gt' => $fromId]]],
                'sort' => [['idSort' => 'asc']],
                'size' => $limit,
            ],
        );

        $hits = $response['hits']['hits'] ?? [];
        if ($hits === []) {
            return [];
        }

        $records = array_map($this->hydrate(...), $hits);
        $bounceIds = array_values(array_unique(array_map(
            static fn (UserMessageBounceRecordInterface $record): int => $record->getBounceId(),
            $records
        )));

        $bouncesById = $this->bounceRepository->findByIdsIndexedById($bounceIds);

        $result = [];
        foreach ($records as $record) {
            $bounce = $bouncesById[$record->getBounceId()] ?? null;

            if ($bounce === null) {
                continue;
            }

            $result[] = ['umb' => $record, 'bounce' => $bounce];
        }

        return $result;
    }

    /**
     * @return array<int, array{
     *   um: UserMessage,
     *   umb: UserMessageBounceRecordInterface|null,
     *   b: Bounce|null
     * }>
     */
    public function getUserMessageHistoryWithBounces(Subscriber $subscriber): array
    {
        $userMessages = $this->userMessageRepository->findSentByUserId((int) $subscriber->getId());

        if ($userMessages === []) {
            return [];
        }

        $docsByMessageId = $this->groupByMessageId($this->fetchDocsByUserId((int) $subscriber->getId()));
        $bouncesById = $this->bounceRepository->findByIdsIndexedById($this->bounceIdsUsedIn($docsByMessageId));

        $result = [];
        foreach ($userMessages as $userMessage) {
            $docs = $docsByMessageId[$userMessage->getMessage()->getId()] ?? [];

            if ($docs === []) {
                $result[] = ['um' => $userMessage, 'umb' => null, 'b' => null];
                continue;
            }

            foreach ($docs as $doc) {
                $result[] = [
                    'um' => $userMessage,
                    'umb' => $doc,
                    'b' => $bouncesById[$doc->getBounceId()] ?? null,
                ];
            }
        }

        return $result;
    }

    /** @return UserMessageBounceRecordInterface[] */
    private function fetchDocsByUserId(int $userId): array
    {
        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'query' => ['term' => ['userId' => $userId]],
                'sort' => [['idSort' => 'desc']],
                'size' => 10000,
            ],
        );

        $hits = $response['hits']['hits'] ?? [];

        return array_map($this->hydrate(...), $hits);
    }

    /**
     * @param UserMessageBounceRecordInterface[] $docs
     * @return array<int, UserMessageBounceRecordInterface[]>
     */
    private function groupByMessageId(array $docs): array
    {
        $docsByMessageId = [];
        foreach ($docs as $doc) {
            $docsByMessageId[$doc->getMessageId()][] = $doc;
        }

        return $docsByMessageId;
    }

    /**
     * @param array<int, UserMessageBounceRecordInterface[]> $docsByMessageId
     * @return int[]
     */
    private function bounceIdsUsedIn(array $docsByMessageId): array
    {
        $bounceIds = [];
        foreach ($docsByMessageId as $docs) {
            foreach ($docs as $doc) {
                $bounceIds[] = $doc->getBounceId();
            }
        }

        return array_values(array_unique($bounceIds));
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

    /**
     * Aggregates document counts by an exact-match field, restricted to a given set of ids - used to
     * correlate bounce counts (Elasticsearch) with rows from a small, non-"big table" DB query
     * (subscribers in a list, messages owned by an admin) without joining across data stores.
     *
     * @param int[] $ids
     * @return array<int, int> counts keyed by id
     */
    private function countsByTermsField(string $field, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $response = $this->client->search(
            $this->resolvePhysicalIndexName(),
            [
                'size' => 0,
                'query' => ['bool' => ['filter' => [['terms' => [$field => $ids]]]]],
                'aggs' => [
                    'by_field' => [
                        'terms' => ['field' => $field, 'size' => count($ids)],
                    ],
                ],
            ],
        );

        $counts = [];
        foreach ($response['aggregations']['by_field']['buckets'] ?? [] as $bucket) {
            $counts[(int) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return $counts;
    }

    private function resolvePhysicalIndexName(): string
    {
        return $this->indexPrefix . UserMessageBounce::SEARCH_INDEX_NAME;
    }
}
