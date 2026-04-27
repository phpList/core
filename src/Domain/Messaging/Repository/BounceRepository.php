<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\Dto\BounceView;
use PhpList\Core\Domain\Messaging\Model\Filter\BounceFilter;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class BounceRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /** @return Bounce[] */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Returns bounce totals grouped by campaign, matching legacy msgbounces listing data.
     *
     * @param int|null $ownerId Limit results to campaigns owned by this admin when provided.
     * @return array<int, array{messageId: int, subject: string, totalBounces: int}>
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
                'messageId' => (int) $row['messageId'],
                'subject' => $row['subject'],
                'totalBounces' => (int) $row['totalBounces'],
            ],
            $rows
        );
    }

    /**
     * @return PaginatedResult<BounceView>
     */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        $lastId = $filter->getLastId();
        $limit = $filter->getLimit();

        $queryBuilder = $this->createQueryBuilder('b')
            ->select('NEW PhpList\\Core\\Domain\\Messaging\\Model\\Dto\\BounceView(
                b.id,
                b.status,
                b.comment,
                b.date,
                m.id as messageId,
                m.content.subject as messageSubject,
                s.id as subscriberId,
                s.email as subscriberEmail
            )')
            ->leftJoin(UserMessageBounce::class, 'umb', 'ON', 'umb.bounceId = b.id')
            ->leftJoin(Message::class, 'm', 'ON', 'm.id = umb.messageId')
            ->leftJoin(Subscriber::class, 's', 'ON', 's.id = umb.userId');

        if (!($filter instanceof BounceFilter)) {
            throw new InvalidArgumentException('Filter must be an instance of BounceFilter');
        }

        if ($filter->getStatus() !== null) {
            $queryBuilder
                ->andWhere('b.status = :status')
                ->setParameter('status', $filter->getStatus());
        }

        if ($filter->getListId() !== null) {
            $queryBuilder
                ->innerJoin('m.listMessages', 'ml')
                ->innerJoin('ml.subscriberList', 'sl')
                ->andWhere('sl.listId = :listId')
                ->setParameter('listId', $filter->getListId());
        }

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<BounceView> $items */
        $items = $queryBuilder
            ->andWhere('b.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new PaginatedResult(
            items: $items,
            total: $total,
            limit: $limit,
            lastId: $lastId,
        );
    }
}
