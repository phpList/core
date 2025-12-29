<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Configuration\Model\UrlCache;

class UrlCacheRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findByUrlAndLastModified(string $url, ?int $lastModified = 0): ?UrlCache
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.url = :url')
            ->setParameter('url', $url)
            ->andWhere('u.lastModified > :lastModified')
            ->setParameter('lastModified', $lastModified)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return UrlCache[] */
    public function getByUrl(string $url): array
    {
        return $this->findBy(['url' => $url]);
    }
}
