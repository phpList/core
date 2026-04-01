<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;

class LinkTrackRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * @return LinkTrack[]
     */
    public function getByMessageId(int $messageId, int $lastId, ?int $limit = null): array
    {
        $query = $this->createQueryBuilder('lt')
            ->where('lt.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->andWhere('lt.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('lt.id', 'ASC');

        if ($limit !== null) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    public function findByUrlUserIdAndMessageId(string $url, int $userId, int $messageId): ?LinkTrack
    {
        return $this->findOneBy([
            'url' => $url,
            'userId' => $userId,
            'messageId' => $messageId,
        ]);
    }

    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('lt')
            ->select('COUNT(lt.id)')
            ->where('lt.latestClick >= :start')
            ->andWhere('lt.latestClick <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
