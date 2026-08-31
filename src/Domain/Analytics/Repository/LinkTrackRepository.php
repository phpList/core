<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Repository;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
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

    /**
     * @return array<string,int> counts keyed by 'Y-m-d'
     * @throws Exception
     */
    public function countGroupedByDay(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $sql = sprintf(
            'SELECT DATE(latestclick) AS day, COUNT(*) AS cnt FROM %s WHERE latestclick >= :start'
            . ' AND latestclick <= :end GROUP BY DATE(latestclick)',
            $table
        );

        $rows = $connection->executeQuery($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['day']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * @param int[] $messageIds
     * @return array<int,int> unique-clicker counts keyed by message id
     */
    public function countUniqueClickersByMessageIds(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('lt')
            ->select('lt.messageId AS messageId, COUNT(DISTINCT lt.userId) AS cnt')
            ->where('lt.messageId IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->groupBy('lt.messageId')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['messageId']] = (int) $row['cnt'];
        }
        return $result;
    }
}
