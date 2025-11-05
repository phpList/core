<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;

class SubscriberPageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /** @return array{page: SubscribePage, data: SubscribePageData}[] */
    public function findPagesWithData(int $pageId): array
    {
        return $this->createQueryBuilder('p')
            ->select('p AS page, d AS data')
            ->from(SubscribePage::class, 'p')
            ->from(SubscribePageData::class, 'd')
            ->where('p.id = :id')
            ->andWhere('d.id = p.id')
            ->setParameter('id', $pageId)
            ->getQuery()
            ->getResult();
    }
}
