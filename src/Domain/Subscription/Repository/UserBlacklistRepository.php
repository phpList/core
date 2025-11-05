<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;

class UserBlacklistRepository extends AbstractRepository
{
    public function findBlacklistInfoByEmail(string $email): ?UserBlacklist
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $queryBuilder->select('ub.email, ub.added, ubd.data AS reason')
            ->from(UserBlacklist::class, 'ub')
            ->innerJoin(UserBlacklistData::class, 'ubd', 'WITH', 'ub.email = ubd.email')
            ->where('ub.email = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findOneByEmail(string $email): ?UserBlacklist
    {
        return $this->findOneBy([
            'email' => $email,
        ]);
    }
}
