<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Identity;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\Criteria;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

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
     * Removes all expired tokens.
     *
     * This method should be called regularly to clean up the tokens.
     *
     * @return int the number of removed tokens
     */
    public function removeExpired(): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $expiredTokens = $this->createQueryBuilder('at')
            ->where('at.expiry <= :date')
            ->setParameter('date', $now)
            ->getQuery()
            ->getResult();

        $deletedCount = 0;

        foreach ($expiredTokens as $token) {
            $this->getEntityManager()->remove($token);
            $deletedCount++;
        }

        $this->getEntityManager()->flush();

        return $deletedCount;
    }
}
