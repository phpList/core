<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;

class UserBlacklistRepository extends AbstractRepository
{
    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadata $class,
        private readonly int $blacklistGraceTime,
    ) {
        parent::__construct($entityManager, $class);
    }

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

    public function isEmailBlacklisted(string $email, ?bool $immediate = true): bool
    {
        // allow 5 minutes to send the last message acknowledging unsubscription
        $grace = $immediate ? 0 : ((($grTime = $this->blacklistGraceTime) >= 1 && $grTime <= 15) ? $grTime : 5);
        $cutoff = (new DateTimeImmutable())->modify('-' . $grace .' minutes');

        return $this->createQueryBuilder('ub')
            ->where('ub.email = :email')
            ->andWhere('ub.added < :cutoff')
            ->setParameter('email', $email)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
