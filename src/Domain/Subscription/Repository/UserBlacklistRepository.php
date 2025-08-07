<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;

class UserBlacklistRepository extends AbstractRepository
{
    public function getBlacklistInfoByEmail(string $email): ?array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('ub.email, ub.added, ubd.data AS reason')
            ->from(UserBlacklist::class, 'ub')
            ->innerJoin(UserBlacklistData::class, 'ubd', 'WITH', 'ub.email = ubd.email')
            ->where('ub.email = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
