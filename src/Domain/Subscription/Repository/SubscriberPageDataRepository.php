<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;

class SubscriberPageDataRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findByPageAndName(SubscribePage $page, string $name): ?SubscribePageData
    {
        return $this->findOneBy(['id' => $page->getId(), 'name' => $name]);
    }

    /** @return SubscribePageData[] */
    public function getByPage(SubscribePage $page): array
    {
        return $this->findBy(['id' => $page->getId()]);
    }
}
