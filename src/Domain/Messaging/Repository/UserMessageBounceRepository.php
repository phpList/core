<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\Subscription;

class UserMessageBounceRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function getCountByMessageId(int $messageId): int
    {
        return (int) $this->createQueryBuilder('umb')
            ->select('COUNT(umb.id)')
            ->where('umb.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns bounce totals per subscriber for a specific list.
     * This matches the legacy list bounces data shape.
     *
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
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                'subscriber.id AS subscriberId',
                'subscriber.email AS email',
                'subscriber.confirmed AS confirmed',
                'subscriber.blacklisted AS blacklisted',
                'COUNT(umb.id) AS totalBounces'
            )
            ->from(Subscriber::class, 'subscriber')
            ->innerJoin(Subscription::class, 'subscription', 'ON', 'subscription.subscriber = subscriber')
            ->innerJoin(UserMessageBounce::class, 'umb', 'ON', 'umb.userId = subscriber.id')
            ->where('IDENTITY(subscription.subscriberList) = :listId')
            ->setParameter('listId', $listId)
            ->groupBy('subscriber.id, subscriber.email, subscriber.confirmed, subscriber.blacklisted')
            ->orderBy('subscriber.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'subscriber_id' => (int) $row['subscriberId'],
                'email' => (string) $row['email'],
                'confirmed' => (bool) $row['confirmed'],
                'blacklisted' => (bool) $row['blacklisted'],
                'total_bounces' => (int) $row['totalBounces'],
            ],
            $rows
        );
    }

    /**
     * Returns bounce totals grouped by campaign, matching legacy msgbounces listing data.
     *
     * @param int|null $ownerId Limit results to campaigns owned by this admin when provided.
     * @return array<int, array{message_id: int, subject: string, total_bounces: int}>
     */
    public function getCampaignBounceTotals(?int $ownerId = null): array
    {
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('m.id AS messageId', 'm.content.subject AS subject', 'COUNT(umb.bounceId) AS totalBounces')
            ->from(Message::class, 'm')
            ->innerJoin(UserMessageBounce::class, 'umb', 'ON', 'umb.messageId = m.id')
            ->groupBy('m.id, m.content.subject')
            ->orderBy('m.id', 'ASC');

        if ($ownerId !== null) {
            $queryBuilder
                ->andWhere('IDENTITY(m.owner) = :ownerId')
                ->setParameter('ownerId', $ownerId);
        }

        /** @var array<int, array{messageId: string|int, subject: string, totalBounces: string|int}> $rows */
        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'message_id' => (int) $row['messageId'],
                'subject' => $row['subject'],
                'total_bounces' => (int) $row['totalBounces'],
            ],
            $rows
        );
    }

    /**
     * Counts bounces between two dates.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return int
     */
    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('umb')
            ->select('COUNT(umb.id)')
            ->where('umb.createdAt >= :start')
            ->andWhere('umb.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsByMessageIdAndUserId(int $messageId, int $subscriberId): bool
    {
        return (bool) $this->createQueryBuilder('umb')
            ->select('1')
            ->where('umb.messageId = :messageId')
            ->andWhere('umb.userId = :userId')
            ->setParameter('messageId', $messageId)
            ->setParameter('userId', $subscriberId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, array{umb: UserMessageBounce, bounce: Bounce}>
     */
    public function getPaginatedWithJoinNoRelation(int $fromId, int $limit): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('umb', 'bounce')
            ->from(UserMessageBounce::class, 'umb')
            ->innerJoin(Bounce::class, 'bounce', 'WITH', 'bounce.id = umb.bounce')
            ->where('umb.id > :id')
            ->setParameter('id', $fromId)
            ->orderBy('umb.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{
     *   um: UserMessage,
     *   umb: UserMessageBounce|null,
     *   b: Bounce|null
     * }>
     */
    public function getUserMessageHistoryWithBounces(Subscriber $subscriber): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('um', 'umb', 'b')
            ->from(UserMessage::class, 'um')
            ->leftJoin(
                join: UserMessageBounce::class,
                alias: 'umb',
                conditionType: 'WITH',
                condition: 'umb.messageId = IDENTITY(um.message) AND umb.userId = IDENTITY(um.user)'
            )
            ->leftJoin(
                join: Bounce::class,
                alias: 'b',
                conditionType: 'WITH',
                condition: 'b.id = umb.bounceId'
            )
            ->where('um.user = :userId')
            ->andWhere('um.status = :status')
            ->setParameter('userId', $subscriber->getId())
            ->setParameter('status', 'sent')
            ->orderBy('um.entered', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
