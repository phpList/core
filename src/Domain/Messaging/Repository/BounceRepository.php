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
use PhpList\Core\Domain\Messaging\Model\BounceStatus;
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
            if ($filter->getStatus() === 'identified-bounces') {
                $queryBuilder
                    ->andWhere('b.status != :status')
                    ->setParameter('status', BounceStatus::UnidentifiedBounce);
            } else {
                $queryBuilder
                    ->andWhere('b.status = :status')
                    ->setParameter('status', $filter->getStatus());
            }
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
