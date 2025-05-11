<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttributeValue;
use PhpList\Core\Domain\Repository\AbstractRepository;

class SubscriberAttributeValueRepository extends AbstractRepository
{
    public function findOneBySubscriberAndAttribute(
        Subscriber $subscriber,
        SubscriberAttributeDefinition $attributeDefinition
    ): ?SubscriberAttributeValue {
        return $this->findOneBy([
            'subscriber' => $subscriber,
            'attributeDefinition' => $attributeDefinition,
        ]);
    }

    public function findOneBySubscriberIdAndAttributeId(
        int $subscriberId,
        int $attributeDefinitionId
    ): ?SubscriberAttributeValue {
        return $this->createQueryBuilder('sa')
            ->join('sa.subscriber', 's')
            ->join('sa.attributeDefinition', 'ad')
            ->where('s.id = :subscriberId')
            ->andWhere('ad.id = :attributeDefinitionId')
            ->setParameter('subscriberId', $subscriberId)
            ->setParameter('attributeDefinitionId', $attributeDefinitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
