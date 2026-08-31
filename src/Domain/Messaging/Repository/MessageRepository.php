<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\AbstractQuery;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
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

    /** @return Message[] */
    public function getByOwnerId(int $ownerId): array
    {
        return $this->createQueryBuilder('m')
            ->where('IDENTITY(m.owner) = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PaginatedResult<Message>
     * @SuppressWarnings("CyclomaticComplexity")
     * @SuppressWarnings("NPathComplexity")
     *
     */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        $lastId = $filter->getLastId();
        $limit = $filter->getLimit();
        $queryBuilder = $this->createQueryBuilder('m');

        if ($filter instanceof MessageFilter && $filter->getOwner() !== null) {
            // Legacy/imported messages have no owner recorded - treat them as shared rather
            // than invisible, instead of excluding them outright via a strict owner match.
            $queryBuilder->andWhere('(m.owner IS NULL OR IDENTITY(m.owner) = :ownerId)')
                ->setParameter('ownerId', $filter->getOwner()->getId());
        }

        if ($filter instanceof MessageFilter && $filter->getSubject() !== null) {
            $queryBuilder->andWhere('m.content.subject LIKE :subject')
                ->setParameter('subject', '%' . $filter->getSubject() . '%');
        }

        if ($filter instanceof MessageFilter && $filter->getStatus() !== null) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $filter->getStatus()))));
            if (count($statuses) === 1) {
                $queryBuilder->andWhere('m.metadata.status = :status')
                    ->setParameter('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $queryBuilder->andWhere('m.metadata.status IN (:statuses)')
                    ->setParameter('statuses', $statuses);
            }
        }

        $countQb = clone $queryBuilder;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sortOrder = $filter instanceof MessageFilter ? $filter->getSortOrder() : 'asc';
        $comparison = $sortOrder === 'desc' ? '<' : '>';

        if ($lastId > 0) {
            $queryBuilder->andWhere(sprintf('m.id %s :lastId', $comparison))
                ->setParameter('lastId', $lastId);
        }

        /** @var list<Message> $items */
        $items = $queryBuilder
            ->orderBy('m.id', strtoupper($sortOrder))
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
            ->set('m.metadata.bounceCount', 'm.bounceCount + 1')
            ->where('m.id = :messageId')
            ->setParameter('messageId', $messageId)
            ->getQuery()
            ->execute();
    }

    /** @return Message[] */
    public function getByStatusAndEmbargo(Message\MessageStatus $status, DateTimeImmutable $embargo): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.metadata.status = :status')
            ->andWhere('m.schedule.embargo IS NULL OR m.schedule.embargo <= :embargo')
            ->setParameter('status', $status->value)
            ->setParameter('embargo', $embargo)
            ->getQuery()
            ->getResult();
    }

    public function findByIdAndStatus(int $id, Message\MessageStatus $status): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('m.id = :id')
            ->andWhere('m.metadata.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNonEmptyFields(int $id): array
    {
        $message = $this->createQueryBuilder('m')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY) ?? [];

        foreach ($message as $key => $value) {
            if ($value === null || $value === '') {
                unset($message[$key]);
            }
        }

        return $message;
    }

    /**
     * Counts active campaigns between two dates.
     * "Active" here means messages that were sent (or in process) during this period.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return int
     */
    public function countActiveBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.metadata.sent >= :start')
            ->andWhere('m.metadata.sent <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
