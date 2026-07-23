<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;

class SubscriberPageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findPageWithData(int $pageId): ?SubscribePage
    {
        $result = $this->createQueryBuilder('p')
            ->select('p AS page', 'd AS data')
            ->leftJoin(
                SubscribePageData::class,
                'd',
                'ON',
                'd.id = p.id'
            )
            ->where('p.id = :id')
            ->setParameter('id', $pageId)
            ->getQuery()
            ->getResult();

        if ($result === []) {
            return null;
        }

        return $this->loadPageData($result);
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        $queryBuilder = $this->createQueryBuilder('p');

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $rows = $queryBuilder
            ->select('p AS page', 'd AS data')
            ->leftJoin(
                SubscribePageData::class,
                'd',
                'ON',
                'd.id = p.id'
            )
            ->andWhere('p.id > :afterId')
            ->setParameter('afterId', $filter->getLastId())
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($filter->getLimit())
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            /** @var SubscribePage $page */
            $page = $row['page'] ?? null;
            $data = $row['data'] ?? null;
            if ($page !== null) {
                $grouped[$page->getId()][] = $row;
            }
            if ($data !== null) {
                $grouped[$data->getId()][] = ['data' => $data];
            }
        }

        $pages = [];
        foreach ($grouped as $group) {
            $pages[] = $this->loadPageData($group);
        }

        return new PaginatedResult(
            items: array_values($pages),
            total: $total,
            limit: $filter->getLimit(),
            lastId: $filter->getLastId(),
        );
    }

    private function loadPageData(array $result): SubscribePage
    {
        /** @var SubscribePage $page */
        $page = array_shift($result)['page'];

        $data = [];
        foreach ($result as $row) {
            if (isset($row['data'])) {
                $data[] = $row['data'];
            }
        }
        $page->setData($data);

        return $page;
    }
}
