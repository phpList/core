<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberAttributeValueFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;

class SubscriberAttributeValueRepository extends AbstractRepository implements PaginatableRepositoryInterface
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

    /**
     * @return SubscriberAttributeValue[]
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        if (!$filter instanceof SubscriberAttributeValueFilter) {
            throw new InvalidArgumentException('Expected SubscriberAttributeValueFilter.');
        }
        $query = $this->createQueryBuilder('sa')
            ->join('sa.subscriber', 's')
            ->join('sa.attributeDefinition', 'ad')
            ->where('ad.id > :lastId')
            ->setParameter('lastId', $lastId);

        if ($filter->getSubscriberId() !== null) {
            $query->andWhere('s.id = :subscriberId')
                ->setParameter('subscriberId', $filter->getSubscriberId());
        }
        return $query->orderBy('ad.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return SubscriberAttributeValue[] */
    public function getForSubscriber(Subscriber $subscriber): array
    {
        return $this->createQueryBuilder('sa')
            ->join('sa.subscriber', 's')
            ->join('sa.attributeDefinition', 'ad')
            ->where('s = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->getQuery()
            ->getResult();
    }
}
