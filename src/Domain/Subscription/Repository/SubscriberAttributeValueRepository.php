<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
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
     * @return PaginatedResult<SubscriberAttributeValue>
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(
        int $lastId,
        int $limit,
        ?FilterRequestInterface $filter = null
    ): PaginatedResult {
        if (!$filter instanceof SubscriberAttributeValueFilter) {
            throw new InvalidArgumentException('Expected SubscriberAttributeValueFilter.');
        }
        $queryBuilder = $this->createQueryBuilder('sav')
            ->join('sav.subscriber', 's')
            ->join('sav.attributeDefinition', 'ad');

        if ($filter->getSubscriberId() !== null) {
            $queryBuilder->andWhere('s.id = :subscriberId')
                ->setParameter('subscriberId', $filter->getSubscriberId());
        }

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT sav.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<SubscriberAttributeValue> $items */
        $items = $queryBuilder
            ->andWhere('sav.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('sav.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new PaginatedResult(
            items: $items,
            total: $total,
            limit: $limit,
            lastId: $lastId,
        );
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

    public function existsByAttributeAndValue(string $tableName, int $optionId): bool
    {
        $row = $this->createQueryBuilder('sa')
            ->join('sa.attributeDefinition', 'ad')
            ->andWhere('ad.tableName = :tableName')
            ->setParameter('tableName', $tableName)
            ->andWhere('sa.value = :value')
            ->setParameter('value', $optionId)
            ->getQuery()
            ->getOneOrNullResult();

        return $row !== null;
    }

    public function findOneBySubscriberAndAttributeName(
        Subscriber $subscriber,
        string $attributeName
    ): ?SubscriberAttributeValue {
        return $this->createQueryBuilder('sa')
            ->join('sa.subscriber', 's')
            ->join('sa.attributeDefinition', 'ad')
            ->where('s = :subscriber')
            ->andWhere('ad.name = :attributeName')
            ->setParameter('subscriber', $subscriber)
            ->setParameter('attributeName', $attributeName)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
