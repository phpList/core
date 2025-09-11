<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Filter\MessageFilter;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;

class MessageRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    /**
     * @return Message[]
     */
    public function findCampaignsWithoutUuid(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.uuid IS NULL OR m.uuid = :emptyString')
            ->setParameter('emptyString', '')
            ->getQuery()
            ->getResult();
    }

    public function getByOwnerId(int $ownerId): array
    {
        return $this->createQueryBuilder('m')
            ->where('IDENTITY(m.owner) = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->getResult();
    }

    /** @return Message[] */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        $queryBuilder = $this->createQueryBuilder('m');

        if ($filter instanceof MessageFilter && $filter->getOwner() !== null) {
            $queryBuilder->andWhere('IDENTITY(m.owner) = :ownerId')
                ->setParameter('ownerId', $filter->getOwner()->getId());
        }

        return $queryBuilder->andWhere('m.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Message[] */
    public function getMessagesByList(SubscriberList $list): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.listMessages', 'lm')
            ->join('lm.subscriberList', 'l')
            ->where('l = :list')
            ->setParameter('list', $list)
            ->getQuery()
            ->getResult();
    }

    public function incrementBounceCount(int $messageId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.bounceCount', 'm.bounceCount + 1')
            ->where('m.id = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->execute();
    }

    public function getByStatusAndEmbargo(Message\MessageStatus $status, DateTimeImmutable $embargo): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.status = :status')
            ->andWhere('m.embargo IS NULL OR m.embargo <= :embargo')
            ->setParameter('status', $status->value)
            ->setParameter('embargo', $embargo)
            ->getQuery()
            ->getResult();
    }
}
