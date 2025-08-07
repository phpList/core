<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;

class UserBlacklistDataRepository extends AbstractRepository
{
    public function findOneByEmail(string $email): ?UserBlacklistData
    {
        return $this->findOneBy(['email' => $email]);
    }
}
