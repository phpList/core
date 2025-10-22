<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\Criteria;
use Exception;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;

/**
 * Repository for AdministratorToken models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class AdministratorTokenRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * Finds one unexpired token by the given key. Returns null if there is no match.
     *
     * This method is intended to check for the validity of a session token.
     *
     * @param string $key
     *
     * @return AdministratorToken|null
     */
    public function findOneUnexpiredByKey(string $key): ?AdministratorToken
    {
        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->eq('key', $key))
            ->andWhere(Criteria::expr()->gt('expiry', new DateTime()));

        $firstMatch = $this->matching($criteria)->first();

        // $firstMatch will be false if there is no match, not null.
        return $firstMatch ?: null;
    }

    /**
     * Get all expired tokens.
     *
     * @return AdministratorToken[]
     * @throws Exception
     */
    public function getExpired(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->createQueryBuilder('at')
            ->where('at.expiry <= :date')
            ->setParameter('date', $now)
            ->getQuery()
            ->getResult();
    }
}
