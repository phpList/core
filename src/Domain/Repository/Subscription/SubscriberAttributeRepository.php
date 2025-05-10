<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\AttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttribute;
use PhpList\Core\Domain\Repository\AbstractRepository;

class SubscriberAttributeRepository extends AbstractRepository
{
    public function findOneBySubscriberAndAttribute(
        Subscriber $subscriber,
        AttributeDefinition $attributeDefinition
    ): ?SubscriberAttribute {
        return $this->findOneBy([
            'subscriber' => $subscriber,
            'attributeDefinition' => $attributeDefinition,
        ]);
    }

    public function findOneBySubscriberIdAndAttributeId(
        int $subscriberId,
        int $attributeDefinitionId
    ): ?SubscriberAttribute {
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
