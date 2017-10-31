<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository\Identity;

use Doctrine\Common\Collections\Criteria;
use PhpList\PhpList4\Domain\Model\Identity\AdministratorToken;
use PhpList\PhpList4\Domain\Repository\AbstractRepository;

/**
 * Repository for AdministratorToken models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepository extends AbstractRepository
{
    /**
     * Finds one unexpired token by the given key. Returns null if there is no match.
     *
     * This method is intended to check for the validity of a session token.
     *
     * @param string $key
     *
     * @return AdministratorToken|null
     */
    public function findOneUnexpiredByKey(string $key)
    {
        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->eq('key', $key))
            ->andWhere(Criteria::expr()->gt('expiry', new \DateTime()));

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
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->delete(AdministratorToken::class, 'token')->where('token.expiry <= CURRENT_TIMESTAMP()');

        return $queryBuilder->getQuery()->execute();
    }
}
