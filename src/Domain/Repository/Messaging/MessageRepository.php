<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Messaging;

use PhpList\Core\Domain\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Filter\MessageFilter;
use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

class MessageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;
    protected ?string $alias = 'm';

    /** @return Message[] */
    public function getByOwnerId(int $ownerId): array
    {
        return $this->createQueryBuilder('m')
            ->where('IDENTITY(m.owner) = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->getResult();
    }

    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        $queryBuilder = $this->createQueryBuilder($this->getAlias());;

        if ($filter instanceof MessageFilter && $filter->getOwner() !== null) {
            $queryBuilder->andWhere("IDENTITY({$this->getAlias()}.owner) = :ownerId")
                ->setParameter('ownerId', $filter->getOwner()->getId());
        }

        return $this->getAfterId($queryBuilder, $lastId, $limit);
    }
}
