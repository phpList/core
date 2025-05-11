<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;

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
