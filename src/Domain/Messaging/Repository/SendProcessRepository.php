<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\SendProcess;

class SendProcessRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function deleteByPage(string $page): void
    {
        $this->createQueryBuilder('sp')
            ->delete()
            ->where('sp.page = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->execute();
    }

    public function countAliveByPage(string $page): int
    {
        return (int)$this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->where('sp.page = :page')
            ->andWhere('sp.alive > 0')
            ->setParameter('page', $page)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNewestAlive(string $page): ?SendProcess
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.page = :page')
            ->andWhere('sp.alive > 0')
            ->setParameter('page', $page)
            ->orderBy('sp.started', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markDeadById(int $id): void
    {
        $this->createQueryBuilder('sp')
            ->update()
            ->set('sp.alive', ':zero')
            ->where('sp.id = :id')
            ->setParameter('zero', 0)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function incrementAlive(int $id): void
    {
        $this->createQueryBuilder('sp')
            ->update()
            ->set('sp.alive', 'sp.alive + 1')
            ->where('sp.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function getAliveValue(int $id): int
    {
        return (int)$this->createQueryBuilder('sp')
            ->select('sp.alive')
            ->where('sp.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
