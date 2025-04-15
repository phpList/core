<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Messaging;

use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Repository\AbstractRepository;

class MessageRepository extends AbstractRepository
{
    /** @return Message[] */
    public function getByOwnerId(int $ownerId): array
    {
        return $this->createQueryBuilder('m')
            ->where('IDENTITY(m.owner) = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->getResult();
    }
}
