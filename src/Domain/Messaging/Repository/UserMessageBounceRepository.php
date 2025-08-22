<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

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

    public function existsByMessageIdAndUserId(int $messageId, int $subscriberId): bool
    {
        $qb = $this->createQueryBuilder('umb')
            ->select('1')
            ->where('umb.messageId = :messageId')
            ->andWhere('umb.userId = :userId')
            ->setParameter('messageId', $messageId)
            ->setParameter('userId', $subscriberId)
            ->setMaxResults(1);

        return (bool) $qb->getQuery()->getOneOrNullResult();
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
        $qb = $this->getEntityManager()
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
            ->orderBy('um.entered', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
