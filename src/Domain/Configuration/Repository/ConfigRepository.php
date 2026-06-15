<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Configuration\Model\Config;

class ConfigRepository extends AbstractRepository
{
    public function findValueByItem(string $name): ?string
    {
        return $this->findOneBy(['key' => $name])?->getValue();
    }

    public function findByKey(string $key): ?Config
    {
        return $this->createQueryBuilder('c')
            ->where('c.key = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
